<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Review;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskService
{
    /* ================================
     * MEMBERSHIP HELPERS
     * ================================ */

    /** Is user an active member of project? */
    public function isActiveMember(int $projectId, int $userId): bool
    {
        return Project::query()
            ->whereKey($projectId)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId)->where('is_active', true))
            ->exists();
    }

    /** Get IDs of projects where user is an active member. */
    public function activeMemberProjectIds(int $userId): array
    {
        return Project::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId)->where('is_active', true))
            ->pluck('id')
            ->all();
    }

    /* ================================
     * ACTIVE ATTEMPTS (TASKS)
     * ================================ */

    protected function baseActiveTaskQuery(int $userId): Builder
    {
        return Task::query()
            ->whereIn('status', ['assigned', 'in_progress'])
            ->where('assigned_to', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            // must still be an active member of the project
            ->whereHas('project.members', fn ($q) => $q->where('user_id', $userId)->where('is_active', true))
            ->orderByRaw('COALESCE(assigned_at, started_at, created_at) ASC')
            ->orderBy('id', 'ASC');
    }

    /** First active task (any project), optionally excluding one. */
    public function firstActiveTaskForUser(int $userId, ?int $exceptTaskId = null): ?Task
    {
        return $this->baseActiveTaskQuery($userId)
            ->when($exceptTaskId, fn ($q) => $q->where('id', '!=', $exceptTaskId))
            ->first();
    }

    /** First active task for user within a project. */
    public function firstActiveTaskForProject(int $projectId, int $userId, ?int $exceptTaskId = null): ?Task
    {
        return $this->baseActiveTaskQuery($userId)
            ->where('project_id', $projectId)
            ->when($exceptTaskId, fn ($q) => $q->where('id', '!=', $exceptTaskId))
            ->first();
    }

    /** Does user have any active task (any project)? */
    public function hasActiveTask(int $userId, ?int $exceptTaskId = null): bool
    {
        return (bool) $this->firstActiveTaskForUser($userId, $exceptTaskId);
    }

    /* ================================
     * ACTIVE REVIEWS
     * ================================ */

    protected function baseActiveReviewQuery(int $userId): Builder
    {
        return Review::query()
            ->where('reviewer_id', $userId)
            ->whereNull('completed_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            // still a member of the review's project
            ->whereHas('annotation.task.project.members', fn ($q) => $q->where('user_id', $userId)->where('is_active', true))
            ->orderByRaw('COALESCE(started_at, created_at) ASC')
            ->orderBy('id', 'ASC');
    }

    /** First active review (any project), optionally excluding one. */
    public function firstActiveReviewForUser(int $userId, ?int $exceptReviewId = null): ?Review
    {
        return $this->baseActiveReviewQuery($userId)
            ->when($exceptReviewId, fn ($q) => $q->where('id', '!=', $exceptReviewId))
            ->first();
    }

    /** First active review in a project. */
    public function firstActiveReviewForProject(int $projectId, int $userId, ?int $exceptReviewId = null): ?Review
    {
        return $this->baseActiveReviewQuery($userId)
            ->whereHas('annotation.task', fn ($q) => $q->where('project_id', $projectId))
            ->when($exceptReviewId, fn ($q) => $q->where('id', '!=', $exceptReviewId))
            ->first();
    }

    /** Does user have any active review (any project)? */
    public function hasActiveReview(int $userId, ?int $exceptReviewId = null): bool
    {
        return (bool) $this->firstActiveReviewForUser($userId, $exceptReviewId);
    }
}
