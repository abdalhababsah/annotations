<?php
// SkipActivityRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\SkipActivity;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface SkipActivityRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUser(User $user): Collection;
    public function findByProject(Project $project): Collection;
    public function getTaskSkips(Project $project): Collection;
    public function getReviewSkips(Project $project): Collection;
    public function getUserSkipStatistics(User $user, Project $project): array;
    public function getProjectSkipSummary(Project $project): array;
    public function getSkippedTasksForUser(User $user, Project $project): array;
}