<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ProjectRepositoryInterface $projectRepository
    ) {}

    /**
     * Display a listing of tasks for a specific project
     */
    public function index(Request $request, int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        // Check permissions
        if (!$this->canViewProjectTasks($project, $user)) {
            abort(403, 'You do not have permission to view tasks for this project.');
        }

        // Build query with filters
        $query = $project->tasks()->with(['audioFile', 'assignee', 'annotations.annotator']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('audioFile', function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = ['created_at', 'updated_at', 'status', 'assigned_at', 'completed_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Paginate results (25 per page for performance)
        $tasks = $query->paginate(25)->withQueryString();

        // Get filter options
        $assigneeOptions = $project->members()
            ->whereIn('role', ['annotator', 'project_admin'])
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->map(fn($member) => [
                'id' => $member->user->id,
                'name' => $member->user->full_name,
                'email' => $member->user->email,
            ]);

        return Inertia::render('Admin/Tasks/Index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'task_time_minutes' => $project->task_time_minutes,
                'review_time_minutes' => $project->review_time_minutes,
            ],
            'tasks' => [
                'data' => $tasks->items(),
                'links' => $tasks->linkCollection(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'from' => $tasks->firstItem(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'to' => $tasks->lastItem(),
                    'total' => $tasks->total(),
                ],
            ],
            'filters' => [
                'status' => $request->status,
                'assigned_to' => $request->assigned_to,
                'search' => $request->search,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'assigneeOptions' => $assigneeOptions,
            'statistics' => $this->getTaskStatistics($project),
        ]);
    }

    /**
     * Show the specified task
     */
    public function show(int $projectId, int $taskId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $task = $this->taskRepository->findOrFail($taskId);
        $user = auth()->user();

        // Verify task belongs to project
        if ($task->project_id !== $project->id) {
            abort(404, 'Task not found in this project.');
        }

        if (!$this->canViewProjectTasks($project, $user)) {
            abort(403, 'You do not have permission to view this task.');
        }

        return Inertia::render('Admin/Tasks/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'assigned_to' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->full_name,
                    'email' => $task->assignee->email,
                ] : null,
                'audio_file' => $task->audioFile ? [
                    'id' => $task->audioFile->id,
                    'original_filename' => $task->audioFile->original_filename,
                    'duration' => $task->audioFile->duration,
                    'formatted_duration' => $task->audioFile->formatted_duration,
                    'file_size' => $task->audioFile->file_size,
                    'formatted_file_size' => $task->audioFile->formatted_file_size,
                    'file_path' => $task->audioFile->file_path,
                ] : null,
                'annotations' => $task->annotations->map(fn($annotation) => [
                    'id' => $annotation->id,
                    'status' => $annotation->status,
                    'annotator' => [
                        'id' => $annotation->annotator->id,
                        'name' => $annotation->annotator->full_name,
                        'email' => $annotation->annotator->email,
                    ],
                    'submitted_at' => $annotation->submitted_at?->format('Y-m-d H:i'),
                    'total_time_spent' => $annotation->total_time_spent,
                    'formatted_time_spent' => $annotation->formatted_time_spent,
                ]),
                'assigned_at' => $task->assigned_at?->format('Y-m-d H:i'),
                'started_at' => $task->started_at?->format('Y-m-d H:i'),
                'completed_at' => $task->completed_at?->format('Y-m-d H:i'),
                'expires_at' => $task->expires_at?->format('Y-m-d H:i'),
                'created_at' => $task->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Assign task to a user
     */
    public function assign(Request $request, int $projectId, int $taskId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $task = $this->taskRepository->findOrFail($taskId);
        $user = auth()->user();

        // Verify permissions
        if (!$this->canManageProjectTasks($project, $user)) {
            abort(403, 'You do not have permission to assign tasks in this project.');
        }

        // Verify task belongs to project and can be assigned
        if ($task->project_id !== $project->id || $task->status !== 'pending') {
            return back()->withErrors(['error' => 'Task cannot be assigned at this time.']);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        // Verify user is a member of the project
        $member = $project->members()
            ->where('user_id', $validated['assigned_to'])
            ->whereIn('role', ['annotator', 'project_admin'])
            ->where('is_active', true)
            ->first();

        if (!$member) {
            return back()->withErrors(['error' => 'Selected user is not an active annotator for this project.']);
        }

        try {
            $this->taskRepository->assignTask($task, $member->user);

            return back()->with('success', 'Task assigned successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to assign task. Please try again.']);
        }
    }

    /**
     * Unassign a task
     */
    public function unassign(int $projectId, int $taskId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $task = $this->taskRepository->findOrFail($taskId);
        $user = auth()->user();

        if (!$this->canManageProjectTasks($project, $user)) {
            abort(403, 'You do not have permission to manage tasks in this project.');
        }

        if ($task->project_id !== $project->id || !in_array($task->status, ['assigned', 'in_progress'])) {
            return back()->withErrors(['error' => 'Task cannot be unassigned at this time.']);
        }

        try {
            $task->update([
                'status' => 'pending',
                'assigned_to' => null,
                'assigned_at' => null,
                'started_at' => null,
                'expires_at' => null,
            ]);

            return back()->with('success', 'Task unassigned successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to unassign task. Please try again.']);
        }
    }

    /**
     * Bulk assign tasks
     */
    public function bulkAssign(Request $request, int $projectId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canManageProjectTasks($project, $user)) {
            abort(403, 'You do not have permission to assign tasks in this project.');
        }

        $validated = $request->validate([
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'exists:tasks,id',
            'assigned_to' => 'required|exists:users,id',
        ]);

        // Verify user is a project member
        $member = $project->members()
            ->where('user_id', $validated['assigned_to'])
            ->whereIn('role', ['annotator', 'project_admin'])
            ->where('is_active', true)
            ->first();

        if (!$member) {
            return back()->withErrors(['error' => 'Selected user is not an active annotator for this project.']);
        }

        try {
            DB::beginTransaction();

            $assignedCount = 0;
            foreach ($validated['task_ids'] as $taskId) {
                $task = $project->tasks()->where('id', $taskId)->where('status', 'pending')->first();
                
                if ($task) {
                    $this->taskRepository->assignTask($task, $member->user);
                    $assignedCount++;
                }
            }

            DB::commit();

            return back()->with('success', "Successfully assigned {$assignedCount} tasks!");

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to assign tasks. Please try again.']);
        }
    }

    /**
     * Get task statistics for the project
     */
    private function getTaskStatistics(Project $project): array
    {
        return [
            'total' => $project->tasks()->count(),
            'pending' => $project->tasks()->where('status', 'pending')->count(),
            'assigned' => $project->tasks()->where('status', 'assigned')->count(),
            'in_progress' => $project->tasks()->where('status', 'in_progress')->count(),
            'completed' => $project->tasks()->where('status', 'completed')->count(),
            'under_review' => $project->tasks()->where('status', 'under_review')->count(),
            'approved' => $project->tasks()->where('status', 'approved')->count(),
            'rejected' => $project->tasks()->where('status', 'rejected')->count(),
            'overdue' => $project->tasks()->expired()->count(),
        ];
    }

    /**
     * Check if user can view project tasks
     */
    private function canViewProjectTasks(Project $project, $user): bool
    {
        return $user->isSystemAdmin() || 
               $user->id === $project->owner_id ||
               $project->members()->where('user_id', $user->id)
                                 ->where('is_active', true)
                                 ->exists();
    }

    /**
     * Check if user can manage project tasks
     */
    private function canManageProjectTasks(Project $project, $user): bool
    {
        return $user->isSystemAdmin() || 
               $user->id === $project->owner_id ||
               $project->members()->where('user_id', $user->id)
                                 ->whereIn('role', ['project_admin'])
                                 ->where('is_active', true)
                                 ->exists();
    }
}