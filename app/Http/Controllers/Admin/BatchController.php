<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Project;
use App\Models\AudioFile;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use App\Services\BatchService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private AudioFileRepositoryInterface $audioFileRepository,
        private BatchService $batchService
    ) {
    }

    /**
     * Display a listing of batches for a project
     */
    public function index(Request $request, int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        $this->authorizeView($project, $user);

        // Build query with filters
        $query = $project->batches()->with(['creator', 'tasks']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSorts = ['created_at', 'updated_at', 'name', 'status', 'completion_percentage'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Paginate results
        // Paginate results
        $perPage = min((int) $request->get('perPage', 15), 100);
        $batches = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Batches/Index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'batches' => [
                // <<< IMPORTANT: array of items, not paginator object
                'data' => $batches
                    ->through(fn($batch) => $this->transformBatchForIndex($batch))
                    ->values(), // keep it a plain array (drops keys)
                'links' => $batches->linkCollection(),
                'meta' => [
                    'current_page' => $batches->currentPage(),
                    'from' => $batches->firstItem(),
                    'last_page' => $batches->lastPage(),
                    'per_page' => $batches->perPage(),
                    'to' => $batches->lastItem(),
                    'total' => $batches->total(),
                ],
            ],
            'statistics' => $this->getBatchStatistics($project),
            'filters' => [
                'status' => $request->status,
                'search' => $request->search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'perPage' => $perPage,
            ],
            'can' => [
                'create' => $this->canManageBatches($project, $user),
                'manage' => $this->canManageBatches($project, $user),
            ],
        ]);

    }

    /**
     * Show the form for creating a new batch
     */
    public function create(int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        return Inertia::render('Admin/Batches/Create', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
        ]);
    }

    /**
     * Store a newly created batch
     */
    public function store(Request $request, int $projectId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $batch = $this->batchService->createBatch($project, $validated, $user);

            return redirect()->route('admin.projects.batches.show', [$project->id, $batch->id])
                ->with('success', 'Batch created successfully! You can now add tasks to it.');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified batch
     */
    public function show(int $projectId, int $batchId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeView($project, $user);

        // Get tasks with pagination
        $tasks = $batch->tasks()
            ->with(['audioFile', 'assignee'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        // Get available audio files for adding tasks
        $availableAudioFiles = $this->batchService->getAvailableAudioFiles($batch)
            ->map(fn($file) => [
                'id' => $file->id,
                'original_filename' => $file->original_filename,
                'duration' => $file->formatted_duration,
                'file_size' => $file->formatted_file_size,
            ]);

        return Inertia::render('Admin/Batches/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'batch' => $this->transformBatchForShow($batch),
            'tasks' => [
                'data' => $tasks->through(fn($task) => $this->transformTaskForShow($task)),
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
            'availableAudioFiles' => $availableAudioFiles,
            'can' => [
                'manage' => $this->canManageBatches($project, $user),
                'publish' => $batch->canBePublished() && $this->canManageBatches($project, $user),
                'pause' => $batch->canBePaused() && $this->canManageBatches($project, $user),
                'resume' => $batch->canBeResumed() && $this->canManageBatches($project, $user),
                'delete' => $batch->canBeDeleted() && $this->canManageBatches($project, $user),
            ],
        ]);
    }

    /**
     * Show the form for editing the batch
     */
    public function edit(int $projectId, int $batchId)
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        if (!$batch->isDraft()) {
            return redirect()->route('admin.projects.batches.show', [$project->id, $batch->id])
                ->with(['error' => 'Only draft batches can be edited.']);
        }

        return Inertia::render('Admin/Batches/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'description' => $batch->description,
                'status' => $batch->status,
                'total_tasks' => $batch->total_tasks,
            ],
        ]);
    }

    /**
     * Update the specified batch
     */
    public function update(Request $request, int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        if (!$batch->isDraft()) {
            return back()->with(['error' => 'Only draft batches can be edited.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $this->batchService->updateBatch($batch, $validated);

            return redirect()->route('admin.projects.batches.show', [$project->id, $batch->id])
                ->with('success', 'Batch updated successfully!');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified batch
     */
    public function destroy(int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        if (!$batch->canBeDeleted()) {
            return back()->with(['error' => 'This batch cannot be deleted.']);
        }

        try {
            $this->batchService->deleteBatch($batch);

            return redirect()->route('admin.projects.batches.index', $project->id)
                ->with('success', 'Batch deleted successfully!');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Publish batch
     */
    public function publish(int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        try {
            $this->batchService->publishBatch($batch);

            return back()->with('success', 'Batch published successfully! Tasks are now available for annotation.');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Pause batch
     */
    public function pause(int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        try {
            $this->batchService->pauseBatch($batch);

            return back()->with('success', 'Batch paused successfully! No new tasks will be assigned.');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Resume batch
     */
    public function resume(int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        try {
            $this->batchService->resumeBatch($batch);

            return back()->with('success', 'Batch resumed successfully! Tasks are now available for annotation.');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Add tasks to batch from audio files
     */
    public function addTasks(Request $request, int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        if (!$batch->isDraft()) {
            $message = 'Tasks can only be added to draft batches.';
            return $request->wantsJson()
                ? back()->with(['error' => $message])
                : back()->with(['error' => $message]);
        }

        // ✅ Clear, strict validation
        $validated = $request->validate(
            [
                'audio_file_ids'   => ['required', 'array', 'min:1'],
                'audio_file_ids.*' => [
                    'bail',
                    'integer',
                    'distinct',
                    \Illuminate\Validation\Rule::exists('audio_files', 'id')->where(fn ($q) =>
                    $q->where('project_id', $project->id)
                    ),
                ],
            ],
            [
                'audio_file_ids.required'          => 'Please select at least one audio file.',
                'audio_file_ids.array'             => 'The audio file payload must be an array of IDs.',
                'audio_file_ids.min'               => 'Please select at least one audio file.',
                'audio_file_ids.*.integer'         => 'Each audio file ID must be an integer.',
                'audio_file_ids.*.distinct'        => 'You have duplicate audio file IDs in your selection.',
                'audio_file_ids.*.exists'          => 'One or more audio files do not exist or do not belong to this project.',
            ]
        );

        try {
            // 🔍 Get a detailed result (added / skipped / invalid / duplicates)
            $result = $this->batchService->addTasksToBatchDetailed($batch, $validated['audio_file_ids']);

            // Build user-friendly messages
            $added     = $result['added'] ?? 0;
            $skipped   = $result['skipped_already_in_batch'] ?? [];
            $invalid   = $result['invalid_for_project'] ?? [];
            $duplicates= $result['duplicates_in_request'] ?? [];

            if ($added === 0) {
                // Nothing was added—return rich error context
                $lines = [];
                if (!empty($skipped))    $lines[] = count($skipped)." already in this batch: [".implode(', ', $skipped)."]";
                if (!empty($invalid))    $lines[] = count($invalid)." not in project or invalid: [".implode(', ', $invalid)."]";
                if (!empty($duplicates)) $lines[] = count($duplicates)." duplicate IDs in request: [".implode(', ', $duplicates)."]";

                $detail = $lines ? ' ('.implode(' | ', $lines).')' : '';
                return back()->with(['error' => 'No tasks were added'.$detail]);
            }

            // Success + optional info about skipped/invalid/duplicates
            $successMsg = "Successfully added {$added} task".($added === 1 ? '' : 's')." to the batch!";
            $infoParts  = [];
            if (!empty($skipped))    $infoParts[] = count($skipped).' already existed';
            if (!empty($invalid))    $infoParts[] = count($invalid).' invalid/not in project';
            if (!empty($duplicates)) $infoParts[] = count($duplicates).' duplicates in request';

            $info = $infoParts ? ' (skipped: '.implode(', ', $infoParts).')' : '';

            return back()->with('success', $successMsg.$info);

        } catch (\Throwable $e) {
            // Surface the actual exception for easier debugging.
            return back()->with(['error' => 'Failed to add tasks: '.$e->getMessage()]);
        }
    }

    public function removeTask(int $projectId, int $batchId, int $taskId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $task = $batch->tasks()->findOrFail($taskId);
        $user = auth()->user();
        $this->authorizeManage($project, $user);
        if (!$batch->isDraft()) {
            return back()->with(['error' => 'Tasks can only be removed from draft batches.']);
        }
        if (!in_array($task->status, ['draft', 'pending'], true)) {
            return back()->with(['error' => 'Only draft or pending tasks can be removed from batch.']);
        }
        try {
            $this->batchService->removeTaskFromBatch($batch, $task->id);
            return back()->with('success', 'Task removed from batch successfully!');
        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get batch statistics for dashboard
     */
    public function statistics(int $projectId): JsonResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        $this->authorizeView($project, $user);

        return response()->json($this->getBatchStatistics($project));
    }

    /**
     * Duplicate batch (copy structure but not progress)
     */
    public function duplicate(int $projectId, int $batchId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        try {
            $newBatch = $this->batchService->duplicateBatch($batch, $user);

            return redirect()->route('admin.projects.batches.show', [$project->id, $newBatch->id])
                ->with('success', 'Batch duplicated successfully! You can now customize the copy.');

        } catch (\Exception $e) {
            return back()->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get batch progress summary
     */
    public function progress(int $projectId, int $batchId): JsonResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $batch = Batch::where('project_id', $project->id)->findOrFail($batchId);
        $user = auth()->user();

        $this->authorizeView($project, $user);

        return response()->json($this->batchService->getBatchProgress($batch));
    }

    /**
     * Bulk create batches with tasks from CSV or selection
     */
    public function bulkCreate(Request $request, int $projectId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        $this->authorizeManage($project, $user);

        $validated = $request->validate([
            'batch_prefix' => 'required|string|max:100',
            'description_template' => 'nullable|string|max:500',
            'tasks_per_batch' => 'required|integer|min:1|max:1000',
            'audio_file_ids' => 'required|array|min:1',
            'audio_file_ids.*' => 'exists:audio_files,id',
        ]);

        try {
            DB::beginTransaction();

            $audioFileIds = $validated['audio_file_ids'];
            $tasksPerBatch = $validated['tasks_per_batch'];
            $batches = collect();

            // Split audio files into chunks
            $chunks = array_chunk($audioFileIds, $tasksPerBatch);

            foreach ($chunks as $index => $chunk) {
                $batchNumber = $index + 1;
                $batchData = [
                    'name' => $validated['batch_prefix'] . " #{$batchNumber}",
                    'description' => str_replace('{batch_number}', $batchNumber, $validated['description_template'] ?? ''),
                ];

                $batch = $this->batchService->createBatchWithTasks($project, $batchData, $chunk, $user);
                $batches->push($batch);
            }

            DB::commit();

            return redirect()->route('admin.projects.batches.index', $project->id)
                ->with('success', "Successfully created {$batches->count()} batches with {$tasksPerBatch} tasks each!");

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // Helper methods for permissions
    private function canManageBatches(Project $project, User $user): bool
    {
        return $user->isSystemAdmin() ||
            $user->id === $project->owner_id ||
            $project->members()->where('user_id', $user->id)
                ->where('role', 'project_admin')
                ->where('is_active', true)
                ->exists();
    }

    private function canViewProject(Project $project, User $user): bool
    {
        if ($user->isSystemAdmin())
            return true;
        if ($user->id === $project->owner_id)
            return true;

        return $project->members()->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    private function authorizeManage(Project $project, User $user): void
    {
        if (!$this->canManageBatches($project, $user)) {
            abort(403, 'You do not have permission to manage batches for this project.');
        }
    }

    private function authorizeView(Project $project, User $user): void
    {
        if (!$this->canViewProject($project, $user)) {
            abort(403, 'You do not have permission to view this project.');
        }
    }

    private function getBatchStatistics(Project $project): array
    {
        return $this->batchService->getProjectBatchStatistics($project);
    }

    private function transformBatchForIndex(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'description' => $batch->description,
            'status' => $batch->status,
            'total_tasks' => $batch->total_tasks,
            'completed_tasks' => $batch->completed_tasks,
            'approved_tasks' => $batch->approved_tasks,
            'rejected_tasks' => $batch->rejected_tasks,
            'completion_percentage' => $batch->completion_percentage,
            'created_at' => $batch->created_at->format('Y-m-d H:i'),
            'published_at' => $batch->published_at?->format('Y-m-d H:i'),
            'completed_at' => $batch->completed_at?->format('Y-m-d H:i'),
            'creator' => [
                'id' => $batch->creator->id,
                'name' => $batch->creator->full_name,
            ],
            'can_be_published' => $batch->canBePublished(),
            'can_be_paused' => $batch->canBePaused(),
            'can_be_resumed' => $batch->canBeResumed(),
            'can_be_deleted' => $batch->canBeDeleted(),
        ];
    }

    private function transformBatchForShow(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'description' => $batch->description,
            'status' => $batch->status,
            'total_tasks' => $batch->total_tasks,
            'completed_tasks' => $batch->completed_tasks,
            'approved_tasks' => $batch->approved_tasks,
            'rejected_tasks' => $batch->rejected_tasks,
            'completion_percentage' => $batch->completion_percentage,
            'created_at' => $batch->created_at->format('Y-m-d H:i'),
            'published_at' => $batch->published_at?->format('Y-m-d H:i'),
            'paused_at' => $batch->paused_at?->format('Y-m-d H:i'),
            'completed_at' => $batch->completed_at?->format('Y-m-d H:i'),
            'creator' => [
                'id' => $batch->creator->id,
                'name' => $batch->creator->full_name,
            ],
            'progress' => $batch->getProgressSummary(),
        ];
    }

    private function transformTaskForShow($task): array
    {
        return [
            'id' => $task->id,
            'status' => $task->status,
            'audioFile' => $task->audioFile ? [
                'id' => $task->audioFile->id,
                'original_filename' => $task->audioFile->original_filename,
                'formatted_duration' => $task->audioFile->formatted_duration,
                'formatted_file_size' => $task->audioFile->formatted_file_size,
            ] : null,
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->full_name,
            ] : null,
            'assigned_at' => $task->assigned_at?->format('Y-m-d H:i'),
            'started_at' => $task->started_at?->format('Y-m-d H:i'),
            'completed_at' => $task->completed_at?->format('Y-m-d H:i'),
            'expires_at' => $task->expires_at?->format('Y-m-d H:i'),
        ];
    }
}
