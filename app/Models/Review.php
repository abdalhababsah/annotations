<?php
// Updated Review.php - REMOVED SKIP FIELDS

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'reviewer_id',
        'action',
        'feedback_rating',
        'feedback_comment',
        'started_at',
        'completed_at',
        'expires_at',
        'review_time_spent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function reviewChanges()
    {
        return $this->hasMany(ReviewChange::class);
    }

    public function skipActivities()
    {
        return $this->hasMany(SkipActivity::class, 'annotation_id', 'annotation_id')
                    ->where('activity_type', 'review');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())->whereNull('completed_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('completed_at')->where('expires_at', '>', now());
    }

    // Helper methods
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast() && !$this->completed_at;
    }

    public function getRemainingTimeAttribute()
    {
        if (!$this->expires_at || $this->isExpired() || $this->completed_at) {
            return 0;
        }
        
        return $this->expires_at->diffInMinutes(now());
    }

    public function isCompleted()
    {
        return !is_null($this->completed_at);
    }

    public function getFormattedReviewTimeAttribute()
    {
        if (!$this->review_time_spent) return '00:00';
        
        $minutes = floor($this->review_time_spent / 60);
        $seconds = $this->review_time_spent % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    // Skip logic - creates skip record and deletes review
    public function skipByUser($user, $reason, $description = null)
    {
        // Create skip activity record with task and annotation info
        SkipActivity::skipReview($this->annotation, $user, $reason, $description);
        
        // Reset annotation status back to submitted
        $this->annotation->update(['status' => 'submitted']);
        
        // Delete this review record
        $this->delete();
    }

    // Check if user has skipped review for this annotation
    public function hasUserSkippedReview($userId)
    {
        return SkipActivity::hasUserSkippedReview($userId, $this->annotation_id);
    }

    // Get available annotations for reviewer (excluding skipped ones)
    public static function getAvailableReviewsForUser($userId, $projectId)
    {
        // Get annotations that user has skipped reviewing
        $skippedAnnotationIds = SkipActivity::where('user_id', $userId)
                                          ->where('project_id', $projectId)  
                                          ->where('activity_type', 'review')
                                          ->pluck('annotation_id')
                                          ->toArray();

        // Get submitted annotations in the project, excluding skipped ones
        return Annotation::whereHas('task', function($query) use ($projectId) {
                            $query->where('project_id', $projectId);
                        })
                        ->where('status', 'submitted')
                        ->whereNotIn('id', $skippedAnnotationIds)
                        ->get();
    }

    // Handle expiration - deletes review automatically  
    public function handleExpiration()
    {
        if ($this->isExpired()) {
            // Reset annotation status
            $this->annotation->update(['status' => 'submitted']);
            
            // Delete expired review
            $this->delete();
        }
    }
}
