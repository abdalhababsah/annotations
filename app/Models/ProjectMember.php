<?php
// ProjectMember.php

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
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // App\Models\ProjectMember.php
    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'id' => null,
            'first_name' => '[deleted]',
            'last_name' => 'user',
            'email' => null,
        ]);
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

    public function scopeAnnotators($query)
    {
        return $query->where('role', 'annotator');
    }

    public function scopeReviewers($query)
    {
        return $query->where('role', 'reviewer');
    }

    public function scopeProjectAdmins($query)
    {
        return $query->where('role', 'project_admin');
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
}
