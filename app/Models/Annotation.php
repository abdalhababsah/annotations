<?php

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
        'total_time_spent' => 'integer',
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

    public function values()
    {
        return $this->hasMany(AnnotationValue::class);
    }

    public function comments()
    {
        return $this->hasMany(AnnotationComment::class);
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

    public function scopeByAnnotator($query, $annotatorId)
    {
        return $query->where('annotator_id', $annotatorId);
    }

    // Helper methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isSubmitted()
    {
        return $this->status === 'submitted';
    }

    public function isUnderReview()
    {
        return $this->status === 'under_review';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function getTimeSpentFormattedAttribute()
    {
        if (!$this->total_time_spent) return '0 minutes';
        
        $hours = floor($this->total_time_spent / 60);
        $minutes = $this->total_time_spent % 60;
        
        if ($hours > 0) {
            return sprintf('%d hours %d minutes', $hours, $minutes);
        }
        
        return sprintf('%d minutes', $minutes);
    }
}
