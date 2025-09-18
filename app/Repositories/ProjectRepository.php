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

    /**
     * Get enhanced project statistics for analytics and charts
     */
    public function getEnhancedProjectStatistics(Project $project): array
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

        // Quality metrics calculations
        $totalReviews = collect($reviewStats)->sum('count');
        $approvedReviews = collect($reviewStats)->firstWhere('action', 'approved')?->count ?? 0;
        $rejectedReviews = collect($reviewStats)->firstWhere('action', 'rejected')?->count ?? 0;
        
        $approvalRate = $totalReviews > 0 ? round(($approvedReviews / $totalReviews) * 100, 1) : 0;
        $revisionRate = $totalReviews > 0 ? round(($rejectedReviews / $totalReviews) * 100, 1) : 0;
        
        $totalTasks = array_sum($taskStatusStats);
        $skippedTasks = $project->skipActivities()->where('activity_type', 'task')->count();
        $skipRate = $totalTasks > 0 ? round(($skippedTasks / $totalTasks) * 100, 1) : 0;

        $qualityMetrics = [
            'avg_review_rating' => collect($reviewStats)->avg('avg_rating') ?? 0,
            'approval_rate' => $approvalRate,
            'revision_rate' => $revisionRate,
            'skip_rate' => $skipRate,
        ];

        // Summary calculations
        $completedTasks = ($taskStatusStats['completed'] ?? 0) + ($taskStatusStats['approved'] ?? 0);
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        $avgTasksPerDay = collect($completionData)->avg('count') ?? 0;
        $mostActiveDay = collect($completionData)->sortByDesc('count')->first() ?? ['date' => now()->format('Y-m-d'), 'count' => 0];
        $teamEfficiency = count($memberPerformance) > 0 ? round(collect($memberPerformance)->sum('completed_tasks') / count($memberPerformance), 1) : 0;

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
                'total_tasks' => $totalTasks,
                'completion_rate' => $completionRate,
                'avg_tasks_per_day' => $avgTasksPerDay,
                'most_active_day' => $mostActiveDay,
                'team_efficiency' => $teamEfficiency,
            ]
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