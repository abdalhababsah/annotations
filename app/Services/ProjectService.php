<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\SegmentationLabel;
class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Get projects for the authenticated user based on their role
     */
    public function getUserProjects(User $user): Collection
    {
        if ($user->isSystemAdmin()) {
            return $this->projectRepository->all();
        }

        return $this->projectRepository->getUserProjects($user);
    }

    public function paginateUserProjects(User $user, array $filters)
    {
        $query = Project::with(['owner', 'members', 'tasks.audioFile', 'annotationDimensions', 'segmentationLabels']);
        if (!$user->isSystemAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('members', fn($mq) => $mq->where('user_id', $user->id)->where('is_active', true));
            });
        }

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $query->where(fn($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sort, $direction);

        return $query->paginate(10)->withQueryString();
    }



    /**
     * Transform projects for index display
     */
    /**
     * Collection helper that reuses the single-item transformer.
     */
    public function transformProjectsForIndex(Collection $projects): array
    {
        return $projects
            ->map(fn(Project $p) => $this->transformProjectForIndexItem($p))
            ->toArray();
    }

    /**
     * Calculate project statistics for index page
     */
    public function calculateProjectStatistics(Collection $projects): array
    {
        return [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'draft_projects' => $projects->where('status', 'draft')->count(),
            'incomplete_projects' => $projects->filter(function ($project) {
                if ($project->project_type === 'annotation') {
                    return !$project->annotationDimensions()->exists() || $project->status === 'draft';
                } else {
                    return !$project->segmentationLabels()->exists() || $project->status === 'draft';
                }
            })->count(),
        ];
    }
    /**
     * Determine which step of setup the project is on
     */
    public function determineSetupStep(Project $project, bool $hasConfiguration): int
    {
        if (!$hasConfiguration) {
            return 2; // Need to configure dimensions/labels
        }

        if ($project->status === 'draft') {
            return 3; // Ready for review and activation
        }

        return 0; // Setup complete (active project)
    }


    /**
     * Quick activate a project that has dimensions but is still in draft
     */
    public function quickActivateProject(Project $project): void
    {
        // Check if project has dimensions
        if (!$project->annotationDimensions()->exists()) {
            throw new \Exception('Cannot activate project without annotation dimensions. Complete the setup first.');
        }

        // Check if project is in draft status
        if ($project->status !== 'draft') {
            throw new \Exception('Only draft projects can be activated.');
        }

        $this->projectRepository->update($project->id, ['status' => 'active']);
    }

    /**
     * Create project with owner (Step 1)
     */
    public function createProjectStepOne(array $data, User $creator, ?User $owner = null): Project
    {
        return DB::transaction(function () use ($data, $creator, $owner) {
            $targetOwner = $owner ?? $creator;
            
            // Set default values for segmentation projects
            if ($data['project_type'] === 'segmentation') {
                $data['allow_custom_labels'] = $data['allow_custom_labels'] ?? false;
            }
            
            return $this->projectRepository->createWithOwner($data, $targetOwner);
        });
    }
    public function saveProjectSegmentationLabels(Project $project, array $data): void
    {
        if ($project->project_type !== 'segmentation') {
            throw new \Exception('This method is only for segmentation projects.');
        }

        DB::transaction(function () use ($project, $data) {
            // Remove existing label assignments
            $project->segmentationLabels()->detach();

            $displayOrder = 0;

            // Handle new labels first (create them)
            if (!empty($data['newLabels'])) {
                foreach ($data['newLabels'] as $newLabelData) {
                    $label = SegmentationLabel::create([
                        'name' => $newLabelData['name'],
                        'color' => $newLabelData['color'],
                        'description' => $newLabelData['description'] ?? null,
                        'is_active' => true,
                    ]);

                    // Attach new label to project
                    $project->segmentationLabels()->attach($label->id, [
                        'display_order' => $displayOrder++,
                    ]);
                }
            }

            // Handle selected existing labels
            if (!empty($data['selectedLabels'])) {
                foreach ($data['selectedLabels'] as $selectedLabel) {
                    // Skip if this label was just created (avoid duplicates)
                    if (!$project->segmentationLabels()->where('label_id', $selectedLabel['id'])->exists()) {
                        $project->segmentationLabels()->attach($selectedLabel['id'], [
                            'display_order' => $displayOrder++,
                        ]);
                    }
                }
            }
        });
    }
    public function saveProjectDimensions(Project $project, array $dimensions): void
    {
        if ($project->project_type !== 'annotation') {
            throw new \Exception('This method is only for annotation projects.');
        }

        DB::transaction(function () use ($project, $dimensions) {
            // Delete existing dimensions and their values (cascade will handle dimension values)
            $project->annotationDimensions()->delete();

            // Create new dimensions
            foreach ($dimensions as $index => $dimensionData) {
                $dimension = $project->annotationDimensions()->create([
                    'name' => $dimensionData['name'],
                    'description' => $dimensionData['description'] ?? null,
                    'dimension_type' => $dimensionData['dimension_type'],
                    'scale_min' => $dimensionData['scale_min'] ?? null,
                    'scale_max' => $dimensionData['scale_max'] ?? null,
                    'is_required' => $dimensionData['is_required'] ?? true,
                    'display_order' => $index,
                ]);

                // Create dimension values for categorical dimensions
                if ($dimension->dimension_type === 'categorical' && isset($dimensionData['values'])) {
                    foreach ($dimensionData['values'] as $valueIndex => $valueData) {
                        // Only create if value is not empty
                        if (!empty(trim($valueData['value']))) {
                            $dimension->dimensionValues()->create([
                                'value' => trim($valueData['value']),
                                'label' => trim($valueData['label']) ?: trim($valueData['value']),
                                'display_order' => $valueIndex,
                            ]);
                        }
                    }
                }
            }
        });
    }

    /**
     * Finalize project creation (Step 3)
     */
    public function finalizeProject(Project $project): void
    {
        if ($project->project_type === 'annotation') {
            // Validate that annotation project has dimensions
            if (!$project->annotationDimensions()->exists()) {
                throw new \Exception('Cannot activate annotation project without annotation dimensions. Please add at least one dimension.');
            }
        } elseif ($project->project_type === 'segmentation') {
            // Validate that segmentation project has labels
            if (!$project->segmentationLabels()->exists()) {
                throw new \Exception('Cannot activate segmentation project without segmentation labels. Please select at least one label.');
            }
        }

        $this->projectRepository->update($project->id, ['status' => 'active']);
    }


    /**
     * Transform project for show display
     */
    public function transformProjectForShow(Project $project): array
    {
        $statistics = $this->projectRepository->getProjectStatistics($project);
        $batchStats = $project->getBatchStatistics();
        
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'project_type' => $project->project_type, // annotation or segmentation
            'allow_custom_labels' => $project->allow_custom_labels,
            'ownership_type' => $project->created_by === $project->owner_id ? 'self_created' : 'admin_assigned',
            'quality_threshold' => 0.8,
            'task_time_minutes' => $project->task_time_minutes,
            'review_time_minutes' => $project->review_time_minutes,
            'annotation_guidelines' => $project->annotation_guidelines,
            'total_batches' => $batchStats['total_batches'],
            'deadline' => $project->deadline?->format('Y-m-d'),
            'owner' => [
                'id' => $project->owner->id,
                'name' => $project->owner->full_name,
                'email' => $project->owner->email,
            ],
            'creator' => [
                'id' => $project->creator->id,
                'name' => $project->creator->full_name,
                'email' => $project->creator->email,
            ],
            'assigner' => $project->created_by !== $project->owner_id ? [
                'id' => $project->creator->id,
                'name' => $project->creator->full_name,
                'email' => $project->creator->email,
            ] : null,
            'members' => $project->members->map(fn($member) => [
                'id' => $member->id,
                'user' => [
                    'id' => $member->user->id,
                    'name' => $member->user->full_name,
                    'email' => $member->user->email,
                ],
                'role' => $member->role,
                'is_active' => $member->is_active,
                'workload_limit' => $member->workload_limit,
                'assigned_at' => $member->created_at->format('Y-m-d H:i'),
            ]),
            'statistics' => [
                'total_tasks' => $statistics['total_tasks'],
                'completed_tasks' => $statistics['completed_tasks'],
                'pending_tasks' => $statistics['pending_tasks'],
                'approved_tasks' => $statistics['approved_tasks'],
                'assigned_tasks' => $statistics['assigned_tasks'] ?? 0,
                'in_progress_tasks' => $statistics['in_progress_tasks'] ?? 0,
                'under_review_tasks' => $statistics['under_review_tasks'] ?? 0,
                'rejected_tasks' => $statistics['rejected_tasks'] ?? 0,
                'total_media_files' => $statistics['total_audio_files'],
                'media_breakdown' => [
                    'audio' => $statistics['total_audio_files'],
                ],
                'completion_percentage' => $statistics['completion_percentage'],
                'team_size' => $statistics['team_size'],
                'annotators_count' => $statistics['annotators_count'],
                'reviewers_count' => $statistics['reviewers_count'],
                'task_skips' => $statistics['task_skips'],
                'review_skips' => $statistics['review_skips'],
                'total_audio_duration' => $statistics['total_audio_duration'],
            ],
            'created_at' => $project->created_at->format('Y-m-d H:i'),
            'updated_at' => $project->updated_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * Update project with validation
     */
    public function updateProject(Project $project, array $data, User $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user) {
            // Prevent activation if no dimensions exist
            if (isset($data['status']) && $data['status'] === 'active' && !$project->annotationDimensions()->exists()) {
                throw new \Exception('Cannot activate project without annotation dimensions. Please add dimensions first.');
            }

            if (
                $user->isSystemAdmin() &&
                isset($data['owner_id']) &&
                $data['owner_id'] != $project->owner_id
            ) {

                $newOwner = $this->userRepository->findOrFail($data['owner_id']);
                unset($data['owner_id']);

                $project = $this->projectRepository->update($project->id, $data);
                $project = $this->projectRepository->assignToOwner($project, $newOwner, $user);
            } else {
                unset($data['owner_id']);
                $project = $this->projectRepository->update($project->id, $data);
            }

            return $project;
        });
    }

    /**
     * Delete project with file cleanup
     */
    public function deleteProject(Project $project): void
    {
        if ($project->tasks()->exists()) {
            throw new \Exception('Cannot delete project that contains tasks. Archive it instead.');
        }

        DB::transaction(function () use ($project) {
            // Get audio files through tasks before deletion
            $tasks = $project->tasks()->with('audioFile')->get();
            $audioFiles = $tasks->map(fn($task) => $task->audioFile)->filter()->unique('id');

            // Delete associated audio files from storage
            foreach ($audioFiles as $audioFile) {
                if ($audioFile && Storage::exists($audioFile->file_path)) {
                    Storage::delete($audioFile->file_path);
                }
            }

            $this->projectRepository->delete($project->id);
        });
    }

    /**
     * Duplicate project structure
     */
    public function duplicateProject(Project $project, User $user): Project
    {
        return DB::transaction(function () use ($project, $user) {
            // Create new project with similar structure
            $duplicatedProject = $this->projectRepository->create([
                'name' => $project->name . ' (Copy)',
                'description' => $project->description,
                'status' => 'draft',
                'owner_id' => $user->id,
                'created_by' => $user->id,
                'task_time_minutes' => $project->task_time_minutes,
                'review_time_minutes' => $project->review_time_minutes,
                'annotation_guidelines' => $project->annotation_guidelines,
                'deadline' => null,
            ]);

            // Copy annotation dimensions
            $dimensions = $project->annotationDimensions()->with('dimensionValues')->get();
            foreach ($dimensions as $dimension) {
                $newDimension = $duplicatedProject->annotationDimensions()->create([
                    'name' => $dimension->name,
                    'description' => $dimension->description,
                    'dimension_type' => $dimension->dimension_type,
                    'scale_min' => $dimension->scale_min,
                    'scale_max' => $dimension->scale_max,
                    'is_required' => $dimension->is_required,
                    'display_order' => $dimension->display_order,
                ]);

                // Copy dimension values for categorical dimensions
                if ($dimension->dimension_type === 'categorical') {
                    foreach ($dimension->dimensionValues as $value) {
                        $newDimension->dimensionValues()->create([
                            'value' => $value->value,
                            'label' => $value->label,
                            'display_order' => $value->display_order,
                        ]);
                    }
                }
            }

            // Add creator as project admin
            $duplicatedProject->members()->create([
                'user_id' => $user->id,
                'role' => 'project_admin',
                'assigned_by' => $user->id,
                'is_active' => true,
            ]);

            return $duplicatedProject;
        });
    }

    /**
     * Get available users for project assignment (excluding admins and existing members)
     */
    public function getAvailableUsersForProject(Project $project): SupportCollection
    {
        // Get users who are not already members and are not system admins
        $existingMemberIds = $project->members()->pluck('user_id')->toArray();

        return $this->userRepository->getActiveUsers()
            ->whereNotIn('id', $existingMemberIds)
            ->where('role', '!=', 'system_admin')
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                ];
            });
    }

    /**
     * Add member to project
     */
    public function addMemberToProject(Project $project, array $data, User $assigner): \App\Models\ProjectMember
    {
        if ($project->members()->where('user_id', $data['user_id'])->exists()) {
            throw new \Exception('User is already a member of this project.');
        }

        return $project->members()->create([
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'assigned_by' => $assigner->id,
            'workload_limit' => $data['workload_limit'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update project member
     */
    public function updateProjectMember(Project $project, int $memberId, array $data)
    {
        $member = $project->members()->whereKey($memberId)->firstOrFail();

        // normalize
        $role = $data['role'];
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $member->is_active;

        // allow null; otherwise 1..50
        $workload = $data['workload_limit'] ?? null;
        if ($workload !== null) {
            $workload = (int) $workload;
            if ($workload < 1) {
                throw ValidationException::withMessages([
                    'workload_limit' => 'Workload must be at least 1, or leave it empty.',
                ]);
            }
            if ($workload > 50)
                $workload = 50;
        }

        DB::transaction(function () use ($member, $role, $isActive, $workload) {
            $member->role = $role;
            $member->is_active = $isActive;
            $member->workload_limit = $workload;   // ← THIS LINE FIXES IT
            $member->save();
        });

        return $member->refresh();
    }


    /**
     * Remove member from project
     */
    public function removeMemberFromProject(Project $project, int $memberId): void
    {
        $member = $project->members()->findOrFail($memberId);

        // Prevent removing the project owner
        if ($member->user_id === $project->owner_id) {
            throw new \Exception('Cannot remove the project owner from the team.');
        }

        $member->delete();
    }

    /**
     * Get project setup status
     */
    public function getProjectSetupStatus(Project $project): array
    {
        if ($project->project_type === 'annotation') {
            $hasConfiguration = $project->annotationDimensions()->exists();
            $configurationCount = $project->annotationDimensions()->count();
        } else {
            $hasConfiguration = $project->segmentationLabels()->exists();
            $configurationCount = $project->segmentationLabels()->count();
        }

        return [
            'has_configuration' => $hasConfiguration,
            'configuration_count' => $configurationCount,
            'is_setup_incomplete' => !$hasConfiguration || $project->status === 'draft',
            'can_be_activated' => $hasConfiguration && $project->status === 'draft',
            'setup_step' => $this->determineSetupStep($project, $hasConfiguration),
            'status' => $project->status,
            'project_type' => $project->project_type,
        ];
    }

    /**
     * Update project status with validation
     */
    public function updateProjectStatus(Project $project, string $status): void
    {
        if ($status === 'active') {
            if ($project->project_type === 'annotation' && !$project->annotationDimensions()->exists()) {
                throw new \Exception('Cannot activate annotation project without annotation dimensions. Please add dimensions first.');
            } elseif ($project->project_type === 'segmentation' && !$project->segmentationLabels()->exists()) {
                throw new \Exception('Cannot activate segmentation project without segmentation labels. Please select labels first.');
            }
        }

        $this->projectRepository->update($project->id, ['status' => $status]);
    }

    /**
     * Archive project
     */
    public function archiveProject(Project $project): void
    {
        $this->projectRepository->update($project->id, ['status' => 'archived']);
    }

    /**
     * Restore project from archive
     */
    public function restoreProject(Project $project): void
    {
        // Check if project has proper configuration before restoring to active
        if ($project->project_type === 'annotation') {
            if (!$project->annotationDimensions()->exists()) {
                throw new \Exception('Cannot restore annotation project to active status without annotation dimensions. Please add dimensions first.');
            }
        } elseif ($project->project_type === 'segmentation') {
            if (!$project->segmentationLabels()->exists()) {
                throw new \Exception('Cannot restore segmentation project to active status without segmentation labels. Please add labels first.');
            }
        }
    
        $this->projectRepository->update($project->id, ['status' => 'active']);
    }
    /**
     * Transform ONE project row for the index (works with paginator->through()).
     */
    public function transformProjectForIndexItem(Project $project): array
    {
        // Use eager-loaded relations when available to avoid N+1s
        $tasks = $project->relationLoaded('tasks') ? $project->tasks : $project->tasks()->with('audioFile')->get();
        $members = $project->relationLoaded('members') ? $project->members : $project->members()->get();

        $audioFilesCount = $tasks
            ->map(fn($t) => $t->audioFile)
            ->filter()
            ->unique('id')
            ->count();

        // Check project configuration based on type
        if ($project->project_type === 'annotation') {
            if ($project->relationLoaded('annotationDimensions')) {
                $hasConfiguration = $project->annotationDimensions->isNotEmpty();
                $configurationCount = $project->annotationDimensions->count();
            } else {
                $hasConfiguration = $project->annotationDimensions()->exists();
                $configurationCount = $project->annotationDimensions()->count();
            }
        } else { // segmentation
            if ($project->relationLoaded('segmentationLabels')) {
                $hasConfiguration = $project->segmentationLabels->isNotEmpty();
                $configurationCount = $project->segmentationLabels->count();
            } else {
                $hasConfiguration = $project->segmentationLabels()->exists();
                $configurationCount = $project->segmentationLabels()->count();
            }
        }

        $isIncomplete = !$hasConfiguration || $project->status === 'draft';
        $canBeActivated = $hasConfiguration && $project->status === 'draft';

        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'allow_custom_labels' => $project->allow_custom_labels,
            'completion_percentage' => $project->completion_percentage,
            'team_size' => $members->count(),
            'task_time_minutes' => $project->task_time_minutes,
            'review_time_minutes' => $project->review_time_minutes,
            'audio_files_count' => $audioFilesCount,
            'tasks_count' => $tasks->count(),
            'configuration_count' => $configurationCount,
            'owner' => [
                'id' => $project->owner->id,
                'name' => $project->owner->full_name,
                'email' => $project->owner->email,
            ],
            'deadline' => $project->deadline?->format('Y-m-d'),
            'created_at' => $project->created_at->format('Y-m-d H:i'),
            'updated_at' => $project->updated_at->format('Y-m-d H:i'),

            // setup helpers
            'has_configuration' => $hasConfiguration,
            'is_setup_incomplete' => $isIncomplete,
            'can_be_activated' => $canBeActivated,
            'setup_step' => $this->determineSetupStep($project, $hasConfiguration),
        ];
    }



}