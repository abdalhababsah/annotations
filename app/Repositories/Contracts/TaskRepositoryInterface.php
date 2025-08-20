<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProject(Project $project): Collection;
    public function findByAssignee(User $user): Collection;
    public function findPendingTasks(): Collection;
    public function findOverdueTasks(): Collection;
    public function assignTask(Task $task, User $user): Task;
    public function getTasksWithAnnotations(Project $project): Collection;
    public function createFromMediaFile(array $data): Task;
    public function getUserWorkload(User $user, Project $project): int;
}
