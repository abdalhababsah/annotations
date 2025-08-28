<?php
// Batch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'published_at',
        'paused_at',
        'completed_at',
        'created_by',
        'total_tasks',
        'completed_tasks',
        'approved_tasks',
        'rejected_tasks',
        'completion_percentage',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_percentage' => 'decimal:2',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['published', 'in_progress']);
    }

    // Status check methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isPublished()
    {
        return $this->status === 'published';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPaused()
    {
        return $this->status === 'paused';
    }

    public function canBePublished()
    {
        return $this->isDraft() && $this->total_tasks > 0;
    }

    public function canBePaused()
    {
        return in_array($this->status, ['published', 'in_progress']);
    }

    public function canBeResumed()
    {
        return $this->isPaused();
    }

    public function canBeDeleted()
    {
        return in_array($this->status, ['draft', 'completed']);
    }

    // Publish batch
    public function publish()
    {
        if (!$this->canBePublished()) {
            throw new \Exception('Batch cannot be published. Ensure it has at least one task.');
        }

        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Update all tasks to pending if they're not already assigned
        $this->tasks()->where('status', 'draft')->update(['status' => 'pending']);

        return $this;
    }

    // Pause batch
    public function pause()
    {
        if (!$this->canBePaused()) {
            throw new \Exception('Batch cannot be paused.');
        }

        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $this;
    }

    // Resume batch
    public function resume()
    {
        if (!$this->canBeResumed()) {
            throw new \Exception('Batch cannot be resumed.');
        }

        $this->update([
            'status' => $this->total_tasks === $this->completed_tasks ? 'completed' : 'published',
            'paused_at' => null,
        ]);

        return $this;
    }

    // Update statistics
    public function updateStatistics()
    {
        $taskStats = $this->tasks()
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ("completed", "approved", "rejected") THEN 1 END) as completed,
                COUNT(CASE WHEN status = "approved" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected
            ')
            ->first();

        $this->update([
            'total_tasks' => $taskStats->total,
            'completed_tasks' => $taskStats->completed,
            'approved_tasks' => $taskStats->approved,
            'rejected_tasks' => $taskStats->rejected,
            'completion_percentage' => $taskStats->total > 0 
                ? round(($taskStats->completed / $taskStats->total) * 100, 2) 
                : 0,
        ]);

        // Auto-complete batch if all tasks are done
        if ($this->isInProgress() && $this->total_tasks > 0 && $this->completed_tasks >= $this->total_tasks) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return $this;
    }

    // Get batch progress summary
    public function getProgressSummary()
    {
        return [
            'total_tasks' => $this->total_tasks,
            'completed_tasks' => $this->completed_tasks,
            'approved_tasks' => $this->approved_tasks,
            'rejected_tasks' => $this->rejected_tasks,
            'pending_tasks' => $this->total_tasks - $this->completed_tasks,
            'completion_percentage' => $this->completion_percentage,
            'status' => $this->status,
            'can_be_published' => $this->canBePublished(),
            'can_be_paused' => $this->canBePaused(),
            'can_be_resumed' => $this->canBeResumed(),
            'can_be_deleted' => $this->canBeDeleted(),
        ];
    }

    // Get available tasks for assignment (only from published batches)
    public function getAvailableTasksForUser($userId)
    {
        if (!$this->isInProgress() && !$this->isPublished()) {
            return collect();
        }

        $skippedTaskIds = SkipActivity::getSkippedTasksForUser($userId, $this->project_id);

        return $this->tasks()
            ->where('status', 'pending')
            ->whereNotIn('id', $skippedTaskIds)
            ->get();
    }

    // Boot method to auto-update statistics when tasks change
    protected static function boot()
    {
        parent::boot();

        static::created(function ($batch) {
            $batch->updateStatistics();
        });
    }
}