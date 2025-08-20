<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'project_type',
        'status',
        'owner_id',
        'ownership_type',
        'created_by',
        'assigned_by',
        'quality_threshold',
        'annotation_guidelines',
        'deadline',
    ];

    protected $casts = [
        'quality_threshold' => 'decimal:2',
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

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
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

    public function formLabels()
    {
        return $this->hasMany(FormLabel::class)->where('is_active', true)->orderBy('display_order');
    }

    public function mediaFiles()
    {
        return $this->hasMany(MediaFile::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function qualityMetrics()
    {
        return $this->hasMany(QualityMetric::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('project_type', $type);
    }

    public function scopeByOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    // Helper methods
    public function isSelfCreated()
    {
        return $this->ownership_type === 'self_created';
    }

    public function isAdminAssigned()
    {
        return $this->ownership_type === 'admin_assigned';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function getCompletionPercentageAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;
        
        $completedTasks = $this->tasks()->whereIn('status', ['completed', 'approved'])->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }
}