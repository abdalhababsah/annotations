<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Display a listing of projects for the authenticated user
     */
    public function index(): Response
    {
        $user = auth()->user();
        
        // Get projects based on user role
        if ($user->isSystemAdmin()) {
            $projects = $this->projectRepository->all();
        } else {
            $projects = $this->projectRepository->getUserProjects($user);
        }

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'status' => $project->status,
                    'project_type' => $project->project_type,
                    'ownership_type' => $project->ownership_type,
                    'completion_percentage' => $project->completion_percentage,
                    'team_size' => $project->members->count(),
                    'owner' => [
                        'id' => $project->owner->id,
                        'name' => $project->owner->getFullNameAttribute(),
                        'email' => $project->owner->email,
                    ],
                    'deadline' => $project->deadline?->format('Y-m-d'),
                    'created_at' => $project->created_at->format('Y-m-d H:i'),
                    'updated_at' => $project->updated_at->format('Y-m-d H:i'),
                ];
            }),
            'userRole' => $user->role,
            'canCreateProject' => $user->isSystemAdmin() || $user->isProjectOwner(),
        ]);
    }

    /**
     * Show the form for creating a new project
     */
    public function create(): Response
    {
        $user = auth()->user();
        
        // Only system admins and project owners can create projects
        if (!$user->isSystemAdmin() && !$user->isProjectOwner()) {
            abort(403, 'You do not have permission to create projects.');
        }

        // Get potential project owners for admin assignment
        $projectOwners = [];
        if ($user->isSystemAdmin()) {
            $projectOwners = $this->userRepository->getProjectOwners()->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'name' => $owner->getFullNameAttribute(),
                    'email' => $owner->email,
                ];
            });
        }

        return Inertia::render('Admin/Projects/Create', [
            'projectOwners' => $projectOwners,
            'userRole' => $user->role,
        ]);
    }

    /**
     * Store a newly created project
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        // Validate based on user role
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'project_type' => ['required', Rule::in(['audio', 'image'])],
            'annotation_guidelines' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
        ];

        // Admin can assign projects to other owners
        if ($user->isSystemAdmin()) {
            $rules['owner_id'] = 'nullable|exists:users,id';
        }

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            if ($user->isSystemAdmin() && isset($validated['owner_id'])) {
                // Admin creating project for another owner
                $owner = $this->userRepository->findOrFail($validated['owner_id']);
                
                $projectData = array_merge($validated, [
                    'ownership_type' => 'admin_assigned',
                    'created_by' => $user->id,
                    'assigned_by' => $user->id,
                    'assigned_at' => now(),
                ]);
                
                unset($projectData['owner_id']);
                $project = $this->projectRepository->createWithOwner($projectData, $owner);
                
            } else {
                // Self-created project
                $project = $this->projectRepository->createWithOwner($validated, $user);
            }

            DB::commit();

            return redirect()->route('admin.projects.show', $project)
                           ->with('success', 'Project created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create project. Please try again.']);
        }
    }

    /**
     * Display the specified project
     */
    public function show(int $id): Response
    {
        $project = $this->projectRepository->findOrFail($id);
        $statistics = $this->projectRepository->getProjectStatistics($project);

        return Inertia::render('Admin/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'project_type' => $project->project_type,
                'ownership_type' => $project->ownership_type,
                'quality_threshold' => $project->quality_threshold,
                'annotation_guidelines' => $project->annotation_guidelines,
                'deadline' => $project->deadline?->format('Y-m-d'),
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->getFullNameAttribute(),
                    'email' => $project->owner->email,
                ],
                'creator' => [
                    'id' => $project->creator->id,
                    'name' => $project->creator->getFullNameAttribute(),
                    'email' => $project->creator->email,
                ],
                'assigner' => $project->assigner ? [
                    'id' => $project->assigner->id,
                    'name' => $project->assigner->getFullNameAttribute(),
                    'email' => $project->assigner->email,
                ] : null,
                'members' => $project->members->map(fn($member) => [
                    'id' => $member->id,
                    'user' => [
                        'id' => $member->user->id,
                        'name' => $member->user->getFullNameAttribute(),
                        'email' => $member->user->email,
                    ],
                    'role' => $member->role,
                    'is_active' => $member->is_active,
                    'workload_limit' => $member->workload_limit,
                    'assigned_at' => $member->created_at->format('Y-m-d H:i'),
                ]),
                'annotation_dimensions' => $project->annotationDimensions->map(fn($dimension) => [
                    'id' => $dimension->id,
                    'name' => $dimension->name,
                    'description' => $dimension->description,
                    'dimension_type' => $dimension->dimension_type,
                    'scale_min' => $dimension->scale_min,
                    'scale_max' => $dimension->scale_max,
                    'scale_labels' => $dimension->scale_labels,
                    'form_template' => $dimension->form_template,
                    'is_required' => $dimension->is_required,
                    'display_order' => $dimension->display_order,
                ]),
                'form_labels' => $project->formLabels->map(fn($label) => [
                    'id' => $label->id,
                    'label_name' => $label->label_name,
                    'label_value' => $label->label_value,
                    'description' => $label->description,
                    'suggested_values' => $label->suggested_values,
                    'display_order' => $label->display_order,
                ]),
                'statistics' => $statistics,
                'created_at' => $project->created_at->format('Y-m-d H:i'),
                'updated_at' => $project->updated_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified project
     */
    public function edit(int $id): Response
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        // Check if user can edit this project
        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        // Get potential project owners for admin reassignment
        $projectOwners = [];
        if ($user->isSystemAdmin()) {
            $projectOwners = $this->userRepository->getProjectOwners()->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'name' => $owner->getFullNameAttribute(),
                    'email' => $owner->email,
                ];
            });
        }

        return Inertia::render('Admin/Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'project_type' => $project->project_type,
                'status' => $project->status,
                'quality_threshold' => $project->quality_threshold,
                'annotation_guidelines' => $project->annotation_guidelines,
                'deadline' => $project->deadline?->format('Y-m-d'),
                'owner_id' => $project->owner_id,
            ],
            'projectOwners' => $projectOwners,
            'userRole' => $user->role,
        ]);
    }

    /**
     * Update the specified project
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        // Check if user can edit this project
        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed', 'archived'])],
            'quality_threshold' => 'required|numeric|min:0|max:1',
            'annotation_guidelines' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
        ];

        // Admin can reassign project ownership
        if ($user->isSystemAdmin()) {
            $rules['owner_id'] = 'nullable|exists:users,id';
        }

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            // Handle ownership change by admin
            if ($user->isSystemAdmin() && 
                isset($validated['owner_id']) && 
                $validated['owner_id'] != $project->owner_id) {
                
                $newOwner = $this->userRepository->findOrFail($validated['owner_id']);
                unset($validated['owner_id']);
                
                // Update project data first
                $project = $this->projectRepository->update($id, $validated);
                
                // Then reassign ownership
                $project = $this->projectRepository->assignToOwner($project, $newOwner, $user);
                
            } else {
                // Regular update
                unset($validated['owner_id']); // Remove if present but not admin
                $project = $this->projectRepository->update($id, $validated);
            }

            DB::commit();

            return redirect()->route('admin.projects.show', $project)
                           ->with('success', 'Project updated successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to update project. Please try again.']);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        // Check if user can delete this project
        if (!$this->canDeleteProject($project, $user)) {
            abort(403, 'You do not have permission to delete this project.');
        }

        // Check if project has data that prevents deletion
        if ($project->tasks()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete project that contains tasks. Archive it instead.']);
        }

        try {
            DB::beginTransaction();

            // Delete associated media files from storage
            foreach ($project->mediaFiles as $mediaFile) {
                if (Storage::exists($mediaFile->file_path)) {
                    Storage::delete($mediaFile->file_path);
                }
            }

            // Delete project (cascade will handle related records)
            $this->projectRepository->delete($id);

            DB::commit();

            return redirect()->route('admin.projects.index')
                           ->with('success', 'Project deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to delete project. Please try again.');
        }
    }

    /**
     * Add a team member to the project
     */
    public function addMember(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        // Check if user can manage team
        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => ['required', Rule::in(['annotator', 'reviewer', 'project_admin'])],
            'workload_limit' => 'nullable|integer|min:1|max:50',
        ]);

        // Check if user is already a member
        if ($project->members()->where('user_id', $validated['user_id'])->exists()) {
            return back()->with('error', 'User is already a member of this project.');
        }

        try {
            $member = $project->members()->create([
                'user_id' => $validated['user_id'],
                'role' => $validated['role'],
                'assigned_by' => $user->id,
                'workload_limit' => $validated['workload_limit'],
                'is_active' => true,
            ]);

            // Load the member with its user relationship
            $member->load('user');

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return back()->with('success', 'Team member added successfully!')
                            ->with('member', $member);
            }
            
            return back()->with('success', 'Team member added successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add team member. Please try again.');
        }
    }

    /**
     * Update a team member's role or settings
     */
    public function updateMember(Request $request, int $projectId, int $memberId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        // Check if user can manage team
        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }

        $member = $project->members()->findOrFail($memberId);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['annotator', 'reviewer', 'project_admin'])],
            'workload_limit' => 'nullable|integer|min:1|max:50',
            'is_active' => 'required|boolean',
        ]);

        try {
            $member->update($validated);
            
            // Reload the member with its relations
            $member->load('user');
            
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return back()->with('success', 'Team member updated successfully!')
                            ->with('member', $member);
            }

            return back()->with('success', 'Team member updated successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update team member. Please try again.');
        }
    }

    /**
     * Remove a team member from the project
     */
    public function removeMember(int $projectId, int $memberId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        // Check if user can manage team
        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }

        $member = $project->members()->findOrFail($memberId);

        // Cannot remove project owner
        if ($member->user_id === $project->owner_id) {
            return back()->with('error', 'Cannot remove the project owner from the team.');
        }

        // Check if member has active tasks
        $activeTasks = $member->user->assignedTasks()
                              ->where('project_id', $project->id)
                              ->whereIn('status', ['assigned', 'in_progress'])
                              ->count();

        if ($activeTasks > 0) {
            return back()->with('error', "Cannot remove member who has {$activeTasks} active tasks. Reassign tasks first.");
        }

        try {
            $member->delete();

            return back()->with('success', 'Team member removed successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove team member. Please try again.');
        }
    }

    /**
     * Get project statistics for dashboard
     */
    public function statistics(int $id): Response
    {
        $project = $this->projectRepository->findOrFail($id);
        $statistics = $this->projectRepository->getProjectStatistics($project);

        return Inertia::render('Admin/Projects/Statistics', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'statistics' => $statistics,
        ]);
    }

    /**
     * Archive a project
     */
    public function archive(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to archive this project.');
        }

        try {
            $project = $this->projectRepository->update($id, ['status' => 'archived']);

            return back()->with('success', 'Project archived successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to archive project. Please try again.');
        }
    }

    /**
     * Restore an archived project
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to restore this project.');
        }

        try {
            $project = $this->projectRepository->update($id, ['status' => 'active']);

            return back()->with('success', 'Project restored successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restore project. Please try again.');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function canEditProject(Project $project, User $user): bool
    {
        return $user->isSystemAdmin() || 
               $user->id === $project->owner_id ||
               $project->members()->where('user_id', $user->id)
                                 ->where('role', 'project_admin')
                                 ->where('is_active', true)
                                 ->exists();
    }

    private function canDeleteProject(Project $project, User $user): bool
    {
        return $user->isSystemAdmin() || $user->id === $project->owner_id;
    }

    private function canManageTeam(Project $project, User $user): bool
    {
        return $user->isSystemAdmin() || 
               $user->id === $project->owner_id ||
               $project->members()->where('user_id', $user->id)
                                 ->where('role', 'project_admin')
                                 ->where('is_active', true)
                                 ->exists();
    }
}