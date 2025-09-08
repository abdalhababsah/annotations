<?php
// Updated Task.php with Batch relationship

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'batch_id',
        'audio_file_id',
        'assigned_to',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
    ];
    //Schema::create('tasks', function (Blueprint $table) {
    //    $table->id();
    //    $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
    //    $table->foreignId('audio_file_id')->constrained('audio_files')->onDelete('cascade');
    //    $table->foreignId('assigned_to')->nullable()->constrained('users');
    //    $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'under_review', 'approved', 'rejected'])->default('pending');
    //    $table->timestamp('assigned_at')->nullable();
    //    $table->timestamp('started_at')->nullable();
    //    $table->timestamp('completed_at')->nullable();
    //    $table->timestamp('expires_at')->nullable()->comment('Task expiration time');
    //    $table->timestamps();
    //
    //    $table->index(['project_id', 'status']);
    //    $table->index(['assigned_to', 'status']);
    //    $table->index('expires_at');
    //});
    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function segments(): HasMany
    {
        return $this->hasMany(TaskSegment::class);
    }

    public function customLabels(): HasMany
    {
        return $this->hasMany(TaskCustomLabel::class);
    }

    public function segmentsWithLabels()
    {
        return $this->segments()->withLabels();
    }
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function audioFile()
    {
        return $this->belongsTo(AudioFile::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function annotations()
    {
        return $this->hasMany(Annotation::class);
    }
    public function approvedAnnotation()
    {
        return $this->hasOne(Annotation::class)
            ->where('status', 'approved')
            ->latestOfMany();
    }

    public function latestAnnotation()
    {
        return $this->hasOne(Annotation::class)->latest();
    }

    public function skipActivities()
    {
        return $this->hasMany(SkipActivity::class, 'task_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())->whereIn('status', ['assigned', 'in_progress']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress']);
    }

    public function scopeFromPublishedBatches($query)
    {
        return $query->whereHas('batch', function ($q) {
            $q->whereIn('status', ['published', 'in_progress']);
        });
    }

    // Helper methods
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast() && in_array($this->status, ['assigned', 'in_progress']);
    }

    public function getRemainingTimeAttribute()
    {
        if (!$this->expires_at || $this->isExpired()) {
            return 0;
        }

        return $this->expires_at->diffInMinutes(now());
    }

    public function canBeStarted()
    {
        return $this->status === 'assigned' && !$this->isExpired() && $this->batch && $this->batch->isInProgress();
    }

    public function canBeAssigned()
    {
        return $this->status === 'pending' && !$this->isExpired() && $this->batch && ($this->batch->isInProgress() || $this->batch->isPublished());

    }

    // Skip logic - creates skip record and resets task
    public function skipByUser($user, $reason, $description = null)
    {
        // Create skip activity record with task_id
        SkipActivity::skipTask($this, $user, $reason, $description);

        // Reset task to pending
        $this->update([
            'status' => 'pending',
            'assigned_to' => null,
            'assigned_at' => null,
            'started_at' => null,
            'expires_at' => null,
        ]);
    }

    // Check if user has skipped this task
    public function hasUserSkipped($userId)
    {
        return SkipActivity::hasUserSkippedTask($userId, $this->id);
    }

    // Get available tasks for user (only from published batches, excluding skipped ones)
    public static function getAvailableTasksForUser($userId, $projectId)
    {
        $skippedTaskIds = SkipActivity::getSkippedTasksForUser($userId, $projectId);

        return static::where('project_id', $projectId)
            ->where('status', 'pending')
            ->whereHas('batch', function ($query) {
                $query->whereIn('status', ['published', 'in_progress']);
            })
            ->whereNotIn('id', $skippedTaskIds)
            ->get();
    }

    // Handle expiration - resets task automatically
    public function handleExpiration()
    {
        if ($this->isExpired()) {
            $this->update([
                'status' => 'pending',
                'assigned_to' => null,
                'assigned_at' => null,
                'started_at' => null,
                'expires_at' => null,
            ]);
        }
    }

    // Boot method to update batch statistics when task changes
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($task) {
            if ($task->batch && $task->isDirty('status')) {
                $task->batch->updateStatistics();
            }
        });

        static::created(function ($task) {
            if ($task->batch) {
                $task->batch->updateStatistics();
            }
        });

        static::deleted(function ($task) {
            if ($task->batch) {
                $task->batch->updateStatistics();
            }
        });
    }
}
