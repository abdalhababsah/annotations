<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'media_file_id',
        'assigned_to',
        'task_name',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'due_date',
        'estimated_duration',
        'actual_duration',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'datetime',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function mediaFile()
    {
        return $this->belongsTo(MediaFile::class);
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

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereNotIn('status', ['completed', 'approved']);
    }

    public function scopeByAssignee($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isAssigned()
    {
        return $this->status === 'assigned';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['completed', 'approved']);
    }

    public function getEfficiencyRatioAttribute()
    {
        if (!$this->estimated_duration || !$this->actual_duration) return null;
        return round($this->estimated_duration / $this->actual_duration, 2);
    }
}