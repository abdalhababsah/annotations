<?php
// SkipActivity.php - UPDATED MODEL

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkipActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'activity_type',
        'task_id',
        'annotation_id',
        'skip_reason',
        'skip_description',
        'skipped_at',
    ];

    protected $casts = [
        'skipped_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function annotation()
    {
        return $this->belongsTo(Annotation::class);
    }

    // Scopes
    public function scopeTaskSkips($query)
    {
        return $query->where('activity_type', 'task');
    }

    public function scopeReviewSkips($query)
    {
        return $query->where('activity_type', 'review');
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    // Helper methods
    public function isTaskSkip()
    {
        return $this->activity_type === 'task';
    }

    public function isReviewSkip()
    {
        return $this->activity_type === 'review';
    }

    // Static helper methods
    public static function skipTask($task, $user, $reason, $description = null)
    {
        return static::create([
            'project_id' => $task->project_id,
            'user_id' => $user->id,
            'activity_type' => 'task',
            'task_id' => $task->id,
            'skip_reason' => $reason,
            'skip_description' => $description,
            'skipped_at' => now(),
        ]);
    }

    public static function skipReview($annotation, $user, $reason, $description = null)
    {
        return static::create([
            'project_id' => $annotation->task->project_id,
            'user_id' => $user->id,
            'activity_type' => 'review',
            'task_id' => $annotation->task_id,
            'annotation_id' => $annotation->id,
            'skip_reason' => $reason,
            'skip_description' => $description,
            'skipped_at' => now(),
        ]);
    }

    // Check if user has skipped a specific task
    public static function hasUserSkippedTask($userId, $taskId)
    {
        return static::where('user_id', $userId)
                    ->where('task_id', $taskId)
                    ->where('activity_type', 'task')
                    ->exists();
    }

    // Check if user has skipped review for a specific annotation
    public static function hasUserSkippedReview($userId, $annotationId)
    {
        return static::where('user_id', $userId)
                    ->where('annotation_id', $annotationId)
                    ->where('activity_type', 'review')
                    ->exists();
    }

    // Get tasks that user has skipped in a project
    public static function getSkippedTasksForUser($userId, $projectId)
    {
        return static::where('user_id', $userId)
                    ->where('project_id', $projectId)
                    ->where('activity_type', 'task')
                    ->pluck('task_id')
                    ->toArray();
    }
}
