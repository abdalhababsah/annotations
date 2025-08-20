<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'assigned_by',
        'is_active',
        'workload_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'workload_limit' => 'integer',
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

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeAnnotators($query)
    {
        return $query->where('role', 'annotator');
    }

    public function scopeReviewers($query)
    {
        return $query->where('role', 'reviewer');
    }

    // Helper methods
    public function isAnnotator()
    {
        return $this->role === 'annotator';
    }

    public function isReviewer()
    {
        return $this->role === 'reviewer';
    }

    public function isProjectAdmin()
    {
        return $this->role === 'project_admin';
    }

    public function getCurrentWorkload()
    {
        return $this->user->assignedTasks()
                    ->where('project_id', $this->project_id)
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->count();
    }

    public function canTakeMoreTasks()
    {
        if (!$this->workload_limit) return true;
        return $this->getCurrentWorkload() < $this->workload_limit;
    }
}