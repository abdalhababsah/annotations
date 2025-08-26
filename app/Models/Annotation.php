<?php
// Annotation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'annotator_id',
        'status',
        'started_at',
        'submitted_at',
        'total_time_spent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    // Relationships
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function annotator()
    {
        return $this->belongsTo(User::class, 'annotator_id');
    }

    public function annotationValues()
    {
        return $this->hasMany(AnnotationValue::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function latestReview()
    {
        return $this->hasOne(Review::class)->latest();
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function getFormattedTimeSpentAttribute()
    {
        if (!$this->total_time_spent) return '00:00';
        
        $minutes = floor($this->total_time_spent / 60);
        $seconds = $this->total_time_spent % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function isEditable()
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function canBeReviewed()
    {
        return $this->status === 'submitted';
    }
}
