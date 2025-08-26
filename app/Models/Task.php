<?php
// Updated Task.php - REMOVED SKIP FIELDS

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'audio_file_id',
        'assigned_to',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
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

    public function latestAnnotation()
    {
        return $this->hasOne(Annotation::class)->latest();
    }

    public function skipActivities()
    {
        return $this->hasMany(SkipActivity::class, 'audio_file_id', 'audio_file_id')
                    ->where('activity_type', 'task');
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
        return $this->status === 'assigned' && !$this->isExpired();
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

    // Get available tasks for user (excluding skipped ones)
    public static function getAvailableTasksForUser($userId, $projectId)
    {
        $skippedTaskIds = SkipActivity::getSkippedTasksForUser($userId, $projectId);
        
        return static::where('project_id', $projectId)
                    ->where('status', 'pending')
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
}