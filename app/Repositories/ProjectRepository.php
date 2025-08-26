<?php
// ProjectRepository.php

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
            ->with(['members', 'audioFiles', 'tasks'])
            ->get();
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
        return $this->model->with([
            'tasks' => function ($query) {
                $query->selectRaw('project_id, status, count(*) as count')
                    ->groupBy('project_id', 'status');
            }
        ])->get();
    }

    public function createWithOwner(array $data, User $owner): Project
    {
        $projectData = array_merge($data, [
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
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

        // Get audio files through tasks (correct relationship)
        $tasksWithAudioFiles = $project->tasks()->with('audioFile')->get();
        $audioFiles = $tasksWithAudioFiles->map(fn($task) => $task->audioFile)->filter()->unique('id');
        $totalDuration = $audioFiles->sum('duration') ?? 0;

        // Skip activity statistics
        $skipStats = $project->skipActivities()
            ->selectRaw('activity_type, count(*) as count')
            ->groupBy('activity_type')
            ->pluck('count', 'activity_type')
            ->toArray();

        return [
            'total_tasks' => $project->tasks()->count(),
            'completed_tasks' => $taskStats['completed'] ?? 0,
            'pending_tasks' => $taskStats['pending'] ?? 0,
            'approved_tasks' => $taskStats['approved'] ?? 0,
            'rejected_tasks' => $taskStats['rejected'] ?? 0,
            'in_progress_tasks' => $taskStats['in_progress'] ?? 0,
            'under_review_tasks' => $taskStats['under_review'] ?? 0,
            'assigned_tasks' => $taskStats['assigned'] ?? 0,
            'total_audio_files' => $audioFiles->count(),
            'total_audio_duration' => $totalDuration,
            'task_skips' => $skipStats['task'] ?? 0,
            'review_skips' => $skipStats['review'] ?? 0,
            'completion_percentage' => $project->completion_percentage,
            'team_size' => $project->members()->where('is_active', true)->count(),
            'annotators_count' => $project->members()->where('role', 'annotator')->where('is_active', true)->count(),
            'reviewers_count' => $project->members()->where('role', 'reviewer')->where('is_active', true)->count(),
        ];
    }

    public function getNextTaskForUser(Project $project, int $userId): ?\App\Models\Task
    {
        return $project->getNextTaskForUser($userId);
    }

    public function assignTaskToUser(Project $project, int $taskId, int $userId): ?\App\Models\Task
    {
        return $project->assignTaskToUser($taskId, $userId);
    }

    public function getNextReviewForUser(Project $project, int $userId): ?\App\Models\Annotation
    {
        return $project->getNextReviewForUser($userId);
    }

    public function assignReviewToUser(Project $project, int $annotationId, int $userId): ?\App\Models\Review
    {
        return $project->assignReviewToUser($annotationId, $userId);
    }

    public function getUserSkipStatistics(Project $project, User $user): array
    {
        return [
            'task_skips' => $user->getTaskSkipCount($project->id),
            'review_skips' => $user->getReviewSkipCount($project->id),
        ];
    }
    public function paginateMembers(Project $project, array $filters, int $perPage = 10)
    {
        $q = $project->members()->with('user');

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $q->whereHas('user', fn($u) =>
                $u->where('full_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if (!empty($filters['role']))
            $q->where('role', $filters['role']);
        if (isset($filters['is_active']))
            $q->where('is_active', (bool) $filters['is_active']);

        return $q->orderByDesc('created_at')->paginate($perPage)->withQueryString();
    }
}