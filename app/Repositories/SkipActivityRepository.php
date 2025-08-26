<?php
// SkipActivityRepository.php

namespace App\Repositories;

use App\Models\SkipActivity;
use App\Models\User;
use App\Models\Project;
use App\Repositories\Contracts\SkipActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SkipActivityRepository extends BaseRepository implements SkipActivityRepositoryInterface
{
    public function __construct(SkipActivity $model)
    {
        parent::__construct($model);
    }

    public function findByUser(User $user): Collection
    {
        return $this->model->where('user_id', $user->id)
                          ->with(['project', 'task.audioFile', 'annotation'])
                          ->get();
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['user', 'task.audioFile', 'annotation'])
                          ->get();
    }

    public function getTaskSkips(Project $project): Collection
    {
        return $this->model->taskSkips()
                          ->where('project_id', $project->id)
                          ->with(['user', 'task.audioFile'])
                          ->get();
    }

    public function getReviewSkips(Project $project): Collection
    {
        return $this->model->reviewSkips()
                          ->where('project_id', $project->id)
                          ->with(['user', 'annotation.task.audioFile'])
                          ->get();
    }

    public function getUserSkipStatistics(User $user, Project $project): array
    {
        $taskSkips = $this->model->where('user_id', $user->id)
                                ->where('project_id', $project->id)
                                ->where('activity_type', 'task')
                                ->count();

        $reviewSkips = $this->model->where('user_id', $user->id)
                                 ->where('project_id', $project->id)
                                 ->where('activity_type', 'review')
                                 ->count();

        return [
            'task_skips' => $taskSkips,
            'review_skips' => $reviewSkips,
            'total_skips' => $taskSkips + $reviewSkips,
        ];
    }

    public function getProjectSkipSummary(Project $project): array
    {
        $skips = $this->model->where('project_id', $project->id);

        return [
            'total_skips' => $skips->count(),
            'task_skips' => $skips->where('activity_type', 'task')->count(),
            'review_skips' => $skips->where('activity_type', 'review')->count(),
            'skip_reasons' => $skips->selectRaw('skip_reason, count(*) as count')
                                   ->groupBy('skip_reason')
                                   ->pluck('count', 'skip_reason')
                                   ->toArray(),
        ];
    }

    public function getSkippedTasksForUser(User $user, Project $project): array
    {
        return SkipActivity::getSkippedTasksForUser($user->id, $project->id);
    }
}