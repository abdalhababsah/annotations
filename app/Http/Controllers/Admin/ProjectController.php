<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SegmentationLabel;
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
use Illuminate\Support\Facades\DB;


class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
        private ProjectRepositoryInterface $projectRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

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
                'data' => $pg->through(fn($p) => $this->projectService->transformProjectForIndexItem($p))->items(),
                'links' => $pg->linkCollection(),
                'meta' => [
                    'current_page' => $pg->currentPage(),
                    'last_page' => $pg->lastPage(),
                    'per_page' => $pg->perPage(),
                    'total' => $pg->total(),
                    'from' => $pg->firstItem(),
                    'to' => $pg->lastItem(),
                ],
            ],
            'userRole' => $user->role,
            'canCreateProject' => $user->isSystemAdmin() || $user->isProjectOwner(),
            'statistics' => $this->projectService->calculateProjectStatistics(
                $this->projectService->getUserProjects($user)
            ),
            'filters' => [
                'q' => $request->get('q', ''),
                'status' => $request->get('status'),
                'sort' => $request->get('sort', 'created_at'),
                'direction' => $request->get('direction', 'desc'),
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
            'project_type' => ['required', Rule::in(['annotation', 'segmentation'])],
            'allow_custom_labels' => 'boolean',
            'annotation_guidelines' => 'nullable|string',
            'deadline' => 'nullable|date|after:today',
            'task_time_minutes' => 'required|integer|min:5',
            'review_time_minutes' => 'required|integer|min:5',
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
                ->with('success', 'Project basic info saved! Now configure your project structure.');

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

        if ($project->project_type === 'segmentation') {
            // Segmentation project - show labels configuration
            $availableLabels = SegmentationLabel::where('is_active', true)->get();
            $projectLabels = $project->segmentationLabels()->get();

            return Inertia::render('Admin/Projects/Create/StepTwoSegmentation', [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'project_type' => $project->project_type,
                    'allow_custom_labels' => $project->allow_custom_labels,
                ],
                'availableLabels' => $availableLabels->map(fn($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                ]),
                'selectedLabels' => $projectLabels->map(fn($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                ]),
                'currentStep' => 2,
                'totalSteps' => 3,
            ]);
        }

        // Annotation project - show dimensions configuration
        $existingDimensions = $project->annotationDimensions()->with('dimensionValues')->orderBy('display_order')->get();

        return Inertia::render('Admin/Projects/Create/StepTwo', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_type' => $project->project_type,
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
     * Store step 2 for annotation projects (Annotation Dimensions)
     */
    public function storeStepTwo(Request $request, int $projectId): RedirectResponse
    {
        // dd('storeStepTwo called', $request->all(), $projectId);
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        if ($project->project_type === 'segmentation') {
            return $this->storeSegmentationLabels($request, $project);
        }

        return $this->storeAnnotationDimensions($request, $project);
    }
    /**
     * Store segmentation labels for segmentation projects
     */
    private function storeSegmentationLabels(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'selectedLabels' => 'nullable|array',
            'selectedLabels.*.id' => 'required_with:selectedLabels|exists:segmentation_labels,id',
            'newLabels' => 'nullable|array',
            'newLabels.*.name' => 'required_with:newLabels|string|max:100',
            'newLabels.*.color' => 'required_with:newLabels|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'newLabels.*.description' => 'nullable|string|max:255',
        ], [
            'selectedLabels.*.id.required_with' => 'Selected label ID is required.',
            'newLabels.*.name.required_with' => 'New label name is required.',
        ]);

        // Custom validation: at least one of selectedLabels or newLabels must have items
        $selectedCount = count($validated['selectedLabels'] ?? []);
        $newCount = count($validated['newLabels'] ?? []);

        if ($selectedCount === 0 && $newCount === 0) {
            return back()->withErrors([
                'selectedLabels' => 'At least one label must be selected or created.'
            ]);
        }

        try {
            $this->projectService->saveProjectSegmentationLabels($project, $validated);

            return redirect()->route('admin.projects.create.step-three', $project->id)
                ->with('success', 'Segmentation labels configured successfully! Now review and finalize your project.');

        } catch (\Exception $e) {
            \Log::error('Failed to save project segmentation labels: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to save labels. Please try again.']);
        }
    }

    /**
     * Store annotation dimensions for annotation projects  
     */
    private function storeAnnotationDimensions(Request $request, Project $project): RedirectResponse
    {
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
        ]);

        try {
            $this->projectService->saveProjectDimensions($project, $validated['dimensions']);

            return redirect()->route('admin.projects.create.step-three', $project->id)
                ->with('success', 'Annotation dimensions configured successfully! Now review and finalize your project.');

        } catch (\Exception $e) {
            \Log::error('Failed to save project dimensions: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to save dimensions. Please try again.']);
        }
    }
    public function createSegmentationLabel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:segmentation_labels,name',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $label = SegmentationLabel::create($validated);

            return response()->json([
                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create label'], 422);
        }
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
    // ProjectController.php

    public function createStepThree(int $projectId): Response
    {
        $project = $this->projectRepository->findOrFail($projectId);
        $user = auth()->user();

        if (!$this->canEditProject($project, $user)) {
            abort(403, 'You do not have permission to edit this project.');
        }

        $statistics = $this->projectRepository->getProjectStatistics($project);

        // 🔁 Always include project_type in the returned project payload
        $projectPayload = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'project_type' => $project->project_type, // <-- IMPORTANT
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
            'allow_custom_labels' => $project->allow_custom_labels,
        ];

        if ($project->project_type === 'segmentation') {
            // ✅ Step 3 for segmentation returns segmentationLabels
            $labels = $project->segmentationLabels()
                ->orderBy('project_segmentation_labels.display_order') // if pivot has order
                ->get();

            return Inertia::render('Admin/Projects/Create/StepThree', [
                'project' => $projectPayload,
                'segmentationLabels' => $labels->map(fn($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                ]),
                'statistics' => $statistics,
                'currentStep' => 3,
                'totalSteps' => 3,
            ]);
        }

        // ✅ Step 3 for annotation returns dimensions
        $dimensions = $project->annotationDimensions()
            ->with('dimensionValues')
            ->orderBy('display_order')
            ->get();

        return Inertia::render('Admin/Projects/Create/StepThree', [
            'project' => $projectPayload,
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
    public function show(int $id): Response|RedirectResponse
    {
        $project = $this->projectRepository->findOrFail($id);
        $user = auth()->user();

        if (!$this->canViewProject($project, $user)) {
            abort(403, 'You do not have permission to view this project.');
        }

        // Get enhanced statistics for charts and visualizations
        $enhancedStats = $this->getEnhancedProjectStatistics($project);
        
        // Base payload comes from service
        $projectData = $this->projectService->transformProjectForShow($project);

        // Return data strictly based on project type
        if ($project->project_type === 'segmentation') {
            $labels = $project->segmentationLabels()
                ->orderBy('project_segmentation_labels.display_order')
                ->get();

            // If draft & no labels, bounce back with a clear message
            if ($project->status === 'draft' && $labels->count() === 0) {
                return redirect()->route('admin.projects.index')
                    ->with('warning', "Project '{$project->name}' is incomplete. Please configure segmentation labels to continue.");
            }

            return Inertia::render('Admin/Projects/Show', [
                'project' => array_merge($projectData, [
                    'project_type' => 'segmentation',
                    'allow_custom_labels' => (bool) $project->allow_custom_labels,
                ]),
                'segmentationLabels' => $labels->map(fn ($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                    'description' => $label->description,
                ]),
                'enhancedStats' => $enhancedStats,
            ]);
        }

        // Annotation project
        $dimensions = $project->annotationDimensions()
            ->with('dimensionValues')
            ->orderBy('display_order')
            ->get();

        // If draft & no dimensions, bounce back with a clear message
        if ($project->status === 'draft' && $dimensions->count() === 0) {
            return redirect()->route('admin.projects.index')
                ->with('warning', "Project '{$project->name}' is incomplete. Please configure annotation dimensions to continue.");
        }

        return Inertia::render('Admin/Projects/Show', [
            'project' => array_merge($projectData, [
                'project_type' => 'annotation',
            ]),
            'dimensions' => $dimensions->map(fn ($dimension) => [
                'id' => $dimension->id,
                'name' => $dimension->name,
                'description' => $dimension->description,
                'dimension_type' => $dimension->dimension_type,
                'scale_min' => $dimension->scale_min,
                'scale_max' => $dimension->scale_max,
                'is_required' => $dimension->is_required,
                'display_order' => $dimension->display_order,
                'values' => $dimension->dimensionValues->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'label' => $value->label,
                    'display_order' => $value->display_order,
                ]),
            ]),
            'enhancedStats' => $enhancedStats,
        ]);
    }

    /**
     * Get enhanced project statistics for charts and visualizations
     */
    private function getEnhancedProjectStatistics(Project $project): array
    {
        // Task status distribution
        $taskStatusStats = DB::table('tasks')
            ->where('project_id', $project->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Daily task completion over last 30 days
        $dailyCompletions = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('completed_at', '>=', now()->subDays(30))
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with zero
        $completionData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $completionData[] = [
                'date' => $date,
                'count' => $dailyCompletions->firstWhere('date', $date)?->count ?? 0,
            ];
        }

        // Member performance statistics
        $memberPerformance = DB::table('project_members')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->leftJoin('tasks', function($join) use ($project) {
                $join->on('tasks.assigned_to', '=', 'project_members.user_id')
                     ->where('tasks.project_id', '=', $project->id);
            })
            ->leftJoin('annotations', 'annotations.task_id', '=', 'tasks.id')
            ->where('project_members.project_id', $project->id)
            ->where('project_members.is_active', true)
            ->selectRaw('
                users.id,
                CONCAT(COALESCE(users.first_name, ""), " ", COALESCE(users.last_name, "")) as name,
                users.email,
                project_members.role,
                COUNT(CASE WHEN tasks.status = "completed" THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN tasks.status = "approved" THEN 1 END) as approved_tasks,
                COUNT(CASE WHEN tasks.status IN ("assigned", "in_progress") THEN 1 END) as active_tasks,
                AVG(CASE WHEN annotations.status IN ("submitted", "approved") AND annotations.total_time_spent IS NOT NULL THEN annotations.total_time_spent END) as avg_time_spent
            ')
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email', 'project_members.role')
            ->get()
            ->toArray();

        // Review statistics
        $reviewStats = DB::table('reviews')
            ->join('annotations', 'annotations.id', '=', 'reviews.annotation_id')
            ->join('tasks', 'tasks.id', '=', 'annotations.task_id')
            ->where('tasks.project_id', $project->id)
            ->selectRaw('
                reviews.action,
                count(*) as count,
                AVG(reviews.feedback_rating) as avg_rating,
                AVG(reviews.review_time_spent) as avg_review_time
            ')
            ->groupBy('reviews.action')
            ->get()
            ->toArray();

        // Audio file statistics
        $audioStats = DB::table('audio_files')
            ->where('project_id', $project->id)
            ->selectRaw('
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                SUM(duration) as total_duration,
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration
            ')
            ->first();

        // Batch progress
        $batchProgress = DB::table('batches')
            ->leftJoin('tasks', 'tasks.batch_id', '=', 'batches.id')
            ->where('batches.project_id', $project->id)
            ->selectRaw('
                batches.id,
                batches.name,
                batches.status as batch_status,
                COUNT(tasks.id) as total_tasks,
                COUNT(CASE WHEN tasks.status = "completed" THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN tasks.status = "approved" THEN 1 END) as approved_tasks,
                COUNT(CASE WHEN tasks.status IN ("assigned", "in_progress") THEN 1 END) as active_tasks
            ')
            ->groupBy('batches.id', 'batches.name', 'batches.status')
            ->get()
            ->map(function($batch) {
                $completion = $batch->total_tasks > 0 
                    ? round(($batch->completed_tasks + $batch->approved_tasks) / $batch->total_tasks * 100, 1)
                    : 0;
                return array_merge((array)$batch, ['completion_percentage' => $completion]);
            })
            ->toArray();

        // Weekly activity heatmap (last 12 weeks)
        $weeklyActivity = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('updated_at', '>=', now()->subWeeks(12))
            ->selectRaw('
                YEAR(updated_at) as year,
                WEEK(updated_at) as week,
                DAYOFWEEK(updated_at) as day_of_week,
                count(*) as activity_count
            ')
            ->groupBy('year', 'week', 'day_of_week')
            ->get()
            ->toArray();

        // Quality metrics
        $qualityMetrics = [
            'avg_review_rating' => collect($reviewStats)->avg('avg_rating') ?? 0,
            'approval_rate' => $this->calculateApprovalRate($reviewStats),
            'revision_rate' => $this->calculateRevisionRate($reviewStats),
            'skip_rate' => $this->calculateSkipRate($project),
        ];

        return [
            'taskStatusDistribution' => $taskStatusStats,
            'dailyCompletions' => $completionData,
            'memberPerformance' => $memberPerformance,
            'reviewStats' => $reviewStats,
            'audioStats' => $audioStats,
            'batchProgress' => $batchProgress,
            'weeklyActivity' => $weeklyActivity,
            'qualityMetrics' => $qualityMetrics,
            'summary' => [
                'total_tasks' => array_sum($taskStatusStats),
                'completion_rate' => $this->calculateCompletionRate($taskStatusStats),
                'avg_tasks_per_day' => collect($completionData)->avg('count'),
                'most_active_day' => collect($completionData)->sortByDesc('count')->first(),
                'team_efficiency' => $this->calculateTeamEfficiency($memberPerformance),
            ]
        ];
    }

    private function calculateApprovalRate(array $reviewStats): float
    {
        $total = collect($reviewStats)->sum('count');
        if ($total === 0) return 0;
        
        $approved = collect($reviewStats)->firstWhere('action', 'approved')?->count ?? 0;
        return round(($approved / $total) * 100, 1);
    }

    private function calculateRevisionRate(array $reviewStats): float
    {
        $total = collect($reviewStats)->sum('count');
        if ($total === 0) return 0;
        
        $rejected = collect($reviewStats)->firstWhere('action', 'rejected')?->count ?? 0;
        return round(($rejected / $total) * 100, 1);
    }

    private function calculateSkipRate(Project $project): float
    {
        $totalTasks = $project->tasks()->count();
        if ($totalTasks === 0) return 0;
        
        $skippedTasks = $project->skipActivities()->where('activity_type', 'task')->count();
        return round(($skippedTasks / $totalTasks) * 100, 1);
    }

    private function calculateCompletionRate(array $taskStats): float
    {
        $total = array_sum($taskStats);
        if ($total === 0) return 0;
        
        $completed = ($taskStats['completed'] ?? 0) + ($taskStats['approved'] ?? 0);
        return round(($completed / $total) * 100, 1);
    }

    private function calculateTeamEfficiency(array $memberPerformance): float
    {
        if (empty($memberPerformance)) return 0;
        
        $totalCompleted = collect($memberPerformance)->sum('completed_tasks');
        $totalMembers = count($memberPerformance);
        
        return $totalMembers > 0 ? round($totalCompleted / $totalMembers, 1) : 0;
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

            $statusMessage = match ($validated['status']) {
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