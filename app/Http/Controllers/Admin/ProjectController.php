<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;


class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
        private ProjectRepositoryInterface $projectRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Display a listing of projects for the authenticated user
     */
    
     public function index(Request $request): Response
     {
         $user = auth()->user();
     
         $filters = $request->all();
         $pg = $this->projectService->paginateUserProjects($user, $filters);
     
         return Inertia::render('Admin/Projects/Index', [
             'projects' => [
                 // IMPORTANT: through(...)->items() returns a plain array, not the paginator object
                 'data' => $pg->through(fn($p) => $this->projectService->transformProjectForIndexItem($p))->items(),
                 'links' => $pg->linkCollection(),
                 'meta' => [
                     'current_page' => $pg->currentPage(),
                     'last_page'    => $pg->lastPage(),
                     'per_page'     => $pg->perPage(),     // ← add this
                     'total'        => $pg->total(),
                     'from'         => $pg->firstItem(),
                     'to'           => $pg->lastItem(),
                 ],
             ],
             'userRole' => $user->role,
             'canCreateProject' => $user->isSystemAdmin() || $user->isProjectOwner(),
             'statistics' => $this->projectService->calculateProjectStatistics(
                 $this->projectService->getUserProjects($user)
             ),
             'filters' => [
                 'q'         => $request->get('q',''),
                 'status'    => $request->get('status'),
                 'sort'      => $request->get('sort','created_at'),
                 'direction' => $request->get('direction','desc'),
                 // REMOVE perPage from filters so the client doesn’t keep sending it
                 // 'perPage' => $request->get('perPage', 12),
             ],
         ]);
     }
     

    /**
     * Quick activate a project that has dimensions but is still in draft
     */
    public function quickActivate(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to activate this project.');
        }

        try {
            $this->projectService->quickActivateProject($project);
            return back()->with('success', 'Project activated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show the form for creating a new project (Step 1 of wizard)
     */
    public function create(): Response
    {
        $user = auth()->user();
        
        if (!$user->isSystemAdmin() && !$user->isProjectOwner()) {
            abort(403, 'You do not have permission to create projects.');
        }

        $projectOwners = [];
        if ($user->isSystemAdmin()) {
            $projectOwners = $this->userRepository->getProjectOwners()->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'name' => $owner->full_name,
                    'email' => $owner->email,
                ];
            });
        }

        return Inertia::render('Admin/Projects/Create', [
            'projectOwners' => $projectOwners,
            'userRole' => $user->role,
            'currentStep' => 1,
            'totalSteps' => 3,
        ]);
    }

    /**
     * Store step 1 of project creation (Basic Info)
     */
    public function storeStepOne(Request $request): RedirectResponse
    {
        $user = auth()->user();
        
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'annotation_guidelines' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
            'task_time_minutes' => 'required|integer|min:5|max:180',
            'review_time_minutes' => 'required|integer|min:5|max:60',
        ];

        if ($user->isSystemAdmin()) {
            $rules['owner_id'] = 'nullable|exists:users,id';
        }

        $validated = $request->validate($rules);

        try {
            $owner = null;
            if ($user->isSystemAdmin() && isset($validated['owner_id'])) {
                $owner = $this->userRepository->findOrFail($validated['owner_id']);
                unset($validated['owner_id']);
            }

            $project = $this->projectService->createProjectStepOne($validated, $user, $owner);

            return redirect()->route('admin.projects.create.step-two', $project->id)
                           ->with('success', 'Project basic info saved! Now configure annotation dimensions.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create project. Please try again.']);
        }
    }

    /**
     * Show step 2 of project creation (Annotation Dimensions)
     */
    public function createStepTwo(int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $existingDimensions = $project->annotationDimensions()->with('dimensionValues')->orderBy('display_order')->get();

        return Inertia::render('Admin/Projects/Create/StepTwo', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'dimensions' => $existingDimensions->map(fn($dimension) => [
                'id' => $dimension->id,
                'name' => $dimension->name,
                'description' => $dimension->description,
                'dimension_type' => $dimension->dimension_type,
                'scale_min' => $dimension->scale_min,
                'scale_max' => $dimension->scale_max,
                'is_required' => $dimension->is_required,
                'display_order' => $dimension->display_order,
                'values' => $dimension->dimensionValues->map(fn($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'label' => $value->label,
                    'display_order' => $value->display_order,
                ]),
            ]),
            'currentStep' => 2,
            'totalSteps' => 3,
        ]);
    }

    /**
     * Store step 2 of project creation (Annotation Dimensions)
     */
    public function storeStepTwo(Request $request, int $projectId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $validated = $request->validate([
            'dimensions' => 'required|array|min:1',
            'dimensions.*.name' => 'required|string|max:100',
            'dimensions.*.description' => 'nullable|string',
            'dimensions.*.dimension_type' => ['required', Rule::in(['categorical', 'numeric_scale'])],
            'dimensions.*.scale_min' => 'nullable|integer|min:1',
            'dimensions.*.scale_max' => 'nullable|integer|min:2|max:10',
            'dimensions.*.is_required' => 'boolean',
            'dimensions.*.values' => 'required_if:dimensions.*.dimension_type,categorical|array',
            'dimensions.*.values.*.value' => 'required_with:dimensions.*.values|string|max:100',
            'dimensions.*.values.*.label' => 'nullable|string|max:100',
        ], [
            'dimensions.required' => 'At least one annotation dimension is required.',
            'dimensions.min' => 'At least one annotation dimension is required.',
            'dimensions.*.name.required' => 'Dimension name is required.',
            'dimensions.*.dimension_type.required' => 'Dimension type is required.',
            'dimensions.*.dimension_type.in' => 'Dimension type must be categorical or numeric_scale.',
            'dimensions.*.values.required_if' => 'Categorical dimensions must have at least one value.',
            'dimensions.*.values.*.value.required_with' => 'Value is required when creating dimension values.',
        ]);

        // Additional validation logic (keeping existing validation)
        foreach ($validated['dimensions'] as $index => $dimensionData) {
            if ($dimensionData['dimension_type'] === 'numeric_scale') {
                if (empty($dimensionData['scale_min']) || empty($dimensionData['scale_max'])) {
                    return back()->withErrors([
                        "dimensions.{$index}.scale_min" => 'Scale minimum and maximum are required for numeric dimensions.'
                    ]);
                }
                
                if ($dimensionData['scale_min'] >= $dimensionData['scale_max']) {
                    return back()->withErrors([
                        "dimensions.{$index}.scale_max" => 'Scale maximum must be greater than minimum.'
                    ]);
                }
            }

            if ($dimensionData['dimension_type'] === 'categorical') {
                $hasValidValues = false;
                if (isset($dimensionData['values'])) {
                    foreach ($dimensionData['values'] as $valueData) {
                        if (!empty(trim($valueData['value']))) {
                            $hasValidValues = true;
                            break;
                        }
                    }
                }
                
                if (!$hasValidValues) {
                    return back()->withErrors([
                        "dimensions.{$index}.values" => 'Categorical dimensions must have at least one valid value.'
                    ]);
                }
            }
        }

        try {
            $this->projectService->saveProjectDimensions($project, $validated['dimensions']);

            return redirect()->route('admin.projects.create.step-three', $project->id)
                           ->with('success', 'Annotation dimensions configured successfully! Now review and finalize your project.');

        } catch (\Exception $e) {
            \Log::error('Failed to save project dimensions: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to save dimensions. Please try again.']);
        }
    }

    /**
     * Show step 3 of project creation (Review & Finalize)
     */
    public function createStepThree(int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $dimensions = $project->annotationDimensions()->with('dimensionValues')->orderBy('display_order')->get();
        $statistics = $this->projectRepository->getProjectStatistics($project);

        return Inertia::render('Admin/Projects/Create/StepThree', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'task_time_minutes' => $project->task_time_minutes,
                'review_time_minutes' => $project->review_time_minutes,
                'annotation_guidelines' => $project->annotation_guidelines,
                'deadline' => $project->deadline?->format('Y-m-d'),
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->full_name,
                    'email' => $project->owner->email,
                ],
                'created_at' => $project->created_at->format('Y-m-d H:i'),
            ],
            'dimensions' => $dimensions->map(fn($dimension) => [
                'id' => $dimension->id,
                'name' => $dimension->name,
                'description' => $dimension->description,
                'dimension_type' => $dimension->dimension_type,
                'scale_min' => $dimension->scale_min,
                'scale_max' => $dimension->scale_max,
                'is_required' => $dimension->is_required,
                'values' => $dimension->dimensionValues->map(fn($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'label' => $value->label,
                ]),
            ]),
            'statistics' => $statistics,
            'currentStep' => 3,
            'totalSteps' => 3,
        ]);
    }

    /**
     * Finalize project creation (Step 3)
     */
    public function finalizeProject(Request $request, int $projectId): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        try {
            $this->projectService->finalizeProject($project);

            return redirect()->route('admin.projects.show', $project->id)
                           ->with('success', 'Project created successfully and is now active!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified project
     */
    public function show(int $id): Response|RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canViewProject($project, $user)) {
            abort(403, 'You do not have permission to view this project.');
        }

        $dimensions = $project->annotationDimensions()->with('dimensionValues')->orderBy('display_order')->get();
        
        // Redirect to index with message if project is draft and has no dimensions
        if ($project->status === 'draft' && $dimensions->count() === 0) {
            return redirect()->route('admin.projects.index')
                           ->with('warning', "Project '{$project->name}' is incomplete. Please configure annotation dimensions to continue.");
        }

        $projectData = $this->projectService->transformProjectForShow($project);

        return Inertia::render('Admin/Projects/Show', [
            'project' => array_merge($projectData, [
                'annotation_dimensions' => $dimensions->map(fn($dimension) => [
                    'id' => $dimension->id,
                    'name' => $dimension->name,
                    'description' => $dimension->description,
                    'dimension_type' => $dimension->dimension_type,
                    'scale_min' => $dimension->scale_min,
                    'scale_max' => $dimension->scale_max,
                    'scale_labels' => null,
                    'form_template' => null,
                    'is_required' => $dimension->is_required,
                    'display_order' => $dimension->display_order,
                ]),
                'form_labels' => [], // Empty for now
            ]),
            'dimensions' => $dimensions->map(fn($dimension) => [
                'id' => $dimension->id,
                'name' => $dimension->name,
                'description' => $dimension->description,
                'dimension_type' => $dimension->dimension_type,
                'scale_min' => $dimension->scale_min,
                'scale_max' => $dimension->scale_max,
                'is_required' => $dimension->is_required,
                'display_order' => $dimension->display_order,
                'values' => $dimension->dimensionValues->map(fn($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'label' => $value->label,
                    'display_order' => $value->display_order,
                ]),
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified project
     */
    public function edit(int $id): Response
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $projectOwners = [];
        if ($user->isSystemAdmin()) {
            $projectOwners = $this->userRepository->getProjectOwners()->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'name' => $owner->full_name,
                    'email' => $owner->email,
                ];
            });
        }

        return Inertia::render('Admin/Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'task_time_minutes' => $project->task_time_minutes,
                'review_time_minutes' => $project->review_time_minutes,
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

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed', 'archived'])],
            'task_time_minutes' => 'required|integer|min:5|max:180',
            'review_time_minutes' => 'required|integer|min:5|max:60',
            'annotation_guidelines' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
        ];

        if ($user->isSystemAdmin()) {
            $rules['owner_id'] = 'nullable|exists:users,id';
        }

        $validated = $request->validate($rules);

        try {
            $project = $this->projectService->updateProject($project, $validated, $user);

            return redirect()->route('admin.projects.show', $project)
                           ->with('success', 'Project updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Archive the specified project
     */
    public function archive(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to archive this project.');
        }

        try {
            $this->projectService->archiveProject($project);
            return back()->with('success', 'Project archived successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Restore the specified project from archive
     */
    public function restore(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to restore this project.');
        }

        try {
            $this->projectService->restoreProject($project);
            return back()->with('success', 'Project restored successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canDeleteProject($project, $user)) {
            abort(403, 'You do not have permission to delete this project.');
        }

        try {
            $this->projectService->deleteProject($project);

            return redirect()->route('admin.projects.index')
                           ->with('success', 'Project deleted successfully!');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Add a member to the project team
     */
    public function addMember(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => ['required', Rule::in(['annotator', 'reviewer', 'project_admin'])],
            'workload_limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $member = $this->projectService->addMemberToProject($project, $validated, $user);
            $member->load('user');

            return back()->with('success', 'Team member added successfully!');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update project status with validation
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to update this project status.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed', 'archived'])],
        ]);

        try {
            $this->projectService->updateProjectStatus($project, $validated['status']);

            $statusMessage = match($validated['status']) {
                'active' => 'Project activated successfully!',
                'paused' => 'Project paused successfully!',
                'completed' => 'Project marked as completed!',
                'archived' => 'Project archived successfully!',
                'draft' => 'Project status updated to draft!',
                default => 'Project status updated successfully!'
            };

            return back()->with('success', $statusMessage);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Duplicate a project (copy structure but not data)
     */
    public function duplicate(int $id): RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to duplicate this project.');
        }

        try {
            $duplicatedProject = $this->projectService->duplicateProject($project, $user);

            return redirect()->route('admin.projects.show', $duplicatedProject->id)
                           ->with('success', 'Project duplicated successfully! You can now customize the copy.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to duplicate project. Please try again.']);
        }
    }

    /**
     * Get project setup status for API calls
     */
    public function getSetupStatus(int $id): JsonResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to view this project.');
        }

        $status = $this->projectService->getProjectSetupStatus($project);

        return response()->json($status);
    }

    /**
     * Get available users for team assignment (excluding admins and existing members)
     */
    public function getAvailableUsers(int $id): JsonResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canManageTeam($project, $user)) {
            abort(403, 'You do not have permission to manage this project team.');
        }

        $availableUsers = $this->projectService->getAvailableUsersForProject($project);

        return response()->json([
            'users' => $availableUsers->values()
        ]);
    }

    // Helper methods for permissions
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

    private function canViewProject(Project $project, User $user): bool
    {
        // System admins can view all projects
        if ($user->isSystemAdmin()) {
            return true;
        }

        // Project owners can view their projects
        if ($user->id === $project->owner_id) {
            return true;
        }

        // Project members can view projects they're part of
        return $project->members()->where('user_id', $user->id)
                                 ->where('is_active', true)
                                 ->exists();
    }
}