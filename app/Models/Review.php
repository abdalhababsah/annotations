<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'reviewer_id',
        'overall_quality_score',
        'detailed_feedback',
        'action_taken',
        'reviewed_at',
    ];

    protected $casts = [
        'overall_quality_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
    public function annotation()
    {
        return $this->belongsTo(Annotation::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('action_taken', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('action_taken', 'rejected');
    }

    public function scopeModified($query)
    {
        return $query->where('action_taken', 'modified');
    }

    public function scopeByReviewer($query, $reviewerId)
    {
        return $query->where('reviewer_id', $reviewerId);
    }

    // Helper methods
    public function isApproved()
    {
        return $this->action_taken === 'approved';
    }

    public function isRejected()
    {
        return $this->action_taken === 'rejected';
    }

    public function isModified()
    {
        return $this->action_taken === 'modified';
    }

    public function isReturnedForRevision()
    {
        return $this->action_taken === 'returned_for_revision';
    }

    public function getQualityGradeAttribute()
    {
        if (!$this->overall_quality_score) return 'N/A';
        
        $score = $this->overall_quality_score;
        
        if ($score >= 0.9) return 'Excellent';
        if ($score >= 0.8) return 'Good';
        if ($score >= 0.7) return 'Fair';
        if ($score >= 0.6) return 'Poor';
        
        return 'Very Poor';
    }
}
