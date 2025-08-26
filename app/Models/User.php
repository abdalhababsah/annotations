<?php
// User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
    
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];  

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function ownedProjects()
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members')
                    ->withPivot('role', 'is_active', 'workload_limit', 'assigned_by')
                    ->withTimestamps();
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function annotations()
    {
        return $this->hasMany(Annotation::class, 'annotator_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function uploadedAudioFiles()
    {
        return $this->hasMany(AudioFile::class, 'uploaded_by');
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

    // Helper methods
    public function isSystemAdmin()
    {
        return $this->role === 'system_admin';
    }

    public function isProjectOwner()
    {
        return $this->role === 'project_owner';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function skipActivities()
    {
        return $this->hasMany(SkipActivity::class);
    }
    // Skip statistics
    public function getTaskSkipCount($projectId = null)
    {
        $query = $this->taskSkips();
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        return $query->count();
    }

    public function getReviewSkipCount($projectId = null)
    {
        $query = $this->reviewSkips();
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        return $query->count();
    }
}
