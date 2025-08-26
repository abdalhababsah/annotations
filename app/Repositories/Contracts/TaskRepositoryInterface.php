<?php
// TaskRepositoryInterface.php - FIXED

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
    public function findExpiredTasks(): Collection;
    public function assignTask(Task $task, User $user): Task;
    public function getTasksWithAnnotations(Project $project): Collection;
    public function createFromAudioFile(array $data): Task;
    public function createBulkFromAudioFiles(Project $project, array $audioFileIds): Collection;
    public function getUserWorkload(User $user, Project $project): int;
    public function getAvailableTasksForUser(User $user, Project $project): Collection;
    public function handleExpiredTasks(): int;
    public function getUserTaskHistory(User $user, Project $project): Collection;
    public function getProjectTaskSummary(Project $project): array;
}
