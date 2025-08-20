<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function findByOwner(User $owner): Collection
    {
        return $this->model->where('owner_id', $owner->id)
                          ->with(['members', 'mediaFiles', 'tasks'])
                          ->get();
    }

    public function findByType(string $type): Collection
    {
        return $this->model->where('project_type', $type)->get();
    }

    public function findByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)->get();
    }

    public function getActiveProjects(): Collection
    {
        return $this->model->where('status', 'active')
                          ->with(['owner', 'members'])
                          ->get();
    }

    public function getUserProjects(User $user): Collection
    {
        return $this->model->whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('is_active', true);
        })->orWhere('owner_id', $user->id)->get();
    }

    public function getProjectsWithProgress(): Collection
    {
        return $this->model->with(['tasks' => function ($query) {
            $query->selectRaw('project_id, status, count(*) as count')
                  ->groupBy('project_id', 'status');
        }])->get();
    }

    public function createWithOwner(array $data, User $owner): Project
    {
        $projectData = array_merge($data, [
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'ownership_type' => 'self_created'
        ]);

        $project = $this->create($projectData);

        // Add owner as project admin
        $project->members()->create([
            'user_id' => $owner->id,
            'role' => 'project_admin',
            'assigned_by' => $owner->id,
            'is_active' => true
        ]);

        return $project->load('members', 'annotationDimensions');
    }

    public function assignToOwner(Project $project, User $owner, User $assigner): Project
    {
        $project->update([
            'owner_id' => $owner->id,
            'ownership_type' => 'admin_assigned',
            'assigned_by' => $assigner->id,
            'assigned_at' => now()
        ]);

        // Add new owner as project admin if not already a member
        if (!$project->members()->where('user_id', $owner->id)->exists()) {
            $project->members()->create([
                'user_id' => $owner->id,
                'role' => 'project_admin',
                'assigned_by' => $assigner->id,
                'is_active' => true
            ]);
        }

        return $project->fresh(['members', 'owner']);
    }

    public function getProjectStatistics(Project $project): array
    {
        $taskStats = $project->tasks()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $mediaStats = $project->mediaFiles()
            ->selectRaw('media_type, count(*) as count')
            ->groupBy('media_type')
            ->pluck('count', 'media_type')
            ->toArray();

        return [
            'total_tasks' => $project->tasks()->count(),
            'completed_tasks' => $taskStats['completed'] ?? 0,
            'pending_tasks' => $taskStats['pending'] ?? 0,
            'approved_tasks' => $taskStats['approved'] ?? 0,
            'total_media_files' => $project->mediaFiles()->count(),
            'media_breakdown' => $mediaStats,
            'completion_percentage' => $project->completion_percentage,
            'team_size' => $project->members()->where('is_active', true)->count()
        ];
    }
}
