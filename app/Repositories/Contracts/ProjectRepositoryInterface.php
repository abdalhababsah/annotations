<?php
// ProjectRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface extends BaseRepositoryInterface
{
    public function findByOwner(User $owner): Collection;
    public function findByStatus(string $status): Collection;
    public function getActiveProjects(): Collection;
    public function getUserProjects(User $user): Collection;
    public function getProjectsWithProgress(): Collection;
    public function createWithOwner(array $data, User $owner): Project;
    public function assignToOwner(Project $project, User $owner, User $assigner): Project;
    public function getProjectStatistics(Project $project): array;
    public function getNextTaskForUser(Project $project, int $userId): ?\App\Models\Task;
    public function assignTaskToUser(Project $project, int $taskId, int $userId): ?\App\Models\Task;
    public function getNextReviewForUser(Project $project, int $userId): ?\App\Models\Annotation;
    public function assignReviewToUser(Project $project, int $annotationId, int $userId): ?\App\Models\Review;
    public function getUserSkipStatistics(Project $project, User $user): array;

    public function paginateMembers(Project $project, array $filters, int $perPage = 10);

}