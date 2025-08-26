<?php
// Project.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'owner_id',
        'created_by',
        'task_time_minutes',
        'review_time_minutes',
        'annotation_guidelines',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role', 'is_active', 'workload_limit', 'assigned_by')
            ->withTimestamps();
    }

    public function annotationDimensions()
    {
        return $this->hasMany(AnnotationDimension::class)->orderBy('display_order');
    }

    public function audioFiles()
    {
        return $this->hasMany(AudioFile::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function getCompletionPercentageAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0)
            return 0;

        $completedTasks = $this->tasks()->whereIn('status', ['completed', 'approved'])->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    public function getAnnotatorsAttribute()
    {
        return $this->members()->where('role', 'annotator')->where('is_active', true)->get();
    }

    public function getReviewersAttribute()
    {
        return $this->members()->where('role', 'reviewer')->where('is_active', true)->get();
    }


    // Get next available task for user (excludes skipped tasks)
    public function getNextTaskForUser($userId)
    {
        $skippedTaskIds = SkipActivity::getSkippedTasksForUser($userId, $this->id);

        return $this->tasks()
            ->where('status', 'pending')
            ->whereNotIn('id', $skippedTaskIds)
            ->first();
    }

    // Assign task to user with time limit
    public function assignTaskToUser($taskId, $userId)
    {
        $task = $this->tasks()->find($taskId);

        if (!$task || $task->status !== 'pending') {
            return false;
        }

        // Check if user has skipped this task
        if ($task->hasUserSkipped($userId)) {
            return false;
        }

        // Assign task with expiration time
        $task->update([
            'status' => 'assigned',
            'assigned_to' => $userId,
            'assigned_at' => now(),
            'expires_at' => now()->addMinutes($this->task_time_minutes),
        ]);

        return $task;
    }

    // Get next available annotation for reviewer (excludes skipped reviews)
    public function getNextReviewForUser($userId)
    {
        $skippedAnnotationIds = SkipActivity::where('user_id', $userId)
            ->where('project_id', $this->id)
            ->where('activity_type', 'review')
            ->pluck('annotation_id')
            ->toArray();

        return Annotation::whereHas('task', function ($query) {
            $query->where('project_id', $this->id);
        })
            ->where('status', 'submitted')
            ->whereNotIn('id', $skippedAnnotationIds)
            ->first();
    }

    // Assign review to user with time limit
    public function assignReviewToUser($annotationId, $userId)
    {
        $annotation = Annotation::whereHas('task', function ($query) {
            $query->where('project_id', $this->id);
        })
            ->where('id', $annotationId)
            ->where('status', 'submitted')
            ->first();

        if (!$annotation) {
            return false;
        }

        // Check if user has skipped this review
        if (SkipActivity::hasUserSkippedReview($userId, $annotationId)) {
            return false;
        }

        // Create review with expiration time
        $review = Review::create([
            'annotation_id' => $annotation->id,
            'reviewer_id' => $userId,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($this->review_time_minutes),
        ]);

        // Update annotation status
        $annotation->update(['status' => 'under_review']);

        return $review;
    }
    public function skipActivities()
{
    return $this->hasMany(SkipActivity::class);
}
}