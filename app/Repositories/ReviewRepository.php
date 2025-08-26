<?php
// ReviewRepository.php

namespace App\Repositories;

use App\Models\Review;
use App\Models\Annotation;
use App\Models\User;
use App\Models\Project;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReviewRepository extends BaseRepository implements ReviewRepositoryInterface
{
    public function __construct(Review $model)
    {
        parent::__construct($model);
    }

    public function findByAnnotation(Annotation $annotation): Collection
    {
        return $this->model->where('annotation_id', $annotation->id)
                          ->with(['reviewer', 'reviewChanges'])
                          ->get();
    }

    public function findByReviewer(User $reviewer): Collection
    {
        return $this->model->where('reviewer_id', $reviewer->id)
                          ->with(['annotation.task.project', 'annotation.task.audioFile'])
                          ->get();
    }

    public function findActiveReviews(): Collection
    {
        return $this->model->active()
                          ->with(['annotation.task.project', 'reviewer'])
                          ->get();
    }

    public function findExpiredReviews(): Collection
    {
        return $this->model->expired()
                          ->with(['annotation.task.project', 'reviewer'])
                          ->get();
    }

    public function createReview(Annotation $annotation, User $reviewer): Review
    {
        return DB::transaction(function () use ($annotation, $reviewer) {
            // Create review with expiration
            $review = $this->create([
                'annotation_id' => $annotation->id,
                'reviewer_id' => $reviewer->id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($annotation->task->project->review_time_minutes),
            ]);

            // Update annotation status
            $annotation->update(['status' => 'under_review']);

            return $review->load(['annotation', 'reviewer']);
        });
    }

    public function completeReview(Review $review, array $data): Review
    {
        return DB::transaction(function () use ($review, $data) {
            // Update review
            $review->update([
                'action' => $data['action'],
                'feedback_rating' => $data['feedback_rating'] ?? null,
                'feedback_comment' => $data['feedback_comment'] ?? null,
                'completed_at' => now(),
                'review_time_spent' => $data['review_time_spent'] ?? null,
            ]);

            // Create review changes if any
            if (isset($data['changes']) && is_array($data['changes'])) {
                foreach ($data['changes'] as $change) {
                    $review->reviewChanges()->create($change);
                }
            }

            // Update annotation status based on review action
            $annotationStatus = $data['action'] === 'approved' ? 'approved' : 'rejected';
            $review->annotation->update(['status' => $annotationStatus]);

            // Update task status
            $taskStatus = $data['action'] === 'approved' ? 'approved' : 'rejected';
            $review->annotation->task->update(['status' => $taskStatus]);

            return $review->fresh(['annotation', 'reviewChanges']);
        });
    }

    public function getUserReviewHistory(User $user, Project $project): Collection
    {
        return $this->model->whereHas('annotation.task', function($query) use ($project) {
                            $query->where('project_id', $project->id);
                        })
                        ->where('reviewer_id', $user->id)
                        ->with(['annotation.task.audioFile', 'reviewChanges'])
                        ->orderBy('created_at', 'desc')
                        ->get();
    }

    public function getProjectReviewSummary(Project $project): array
    {
        $reviews = $this->model->whereHas('annotation.task', function($query) use ($project) {
            $query->where('project_id', $project->id);
        });

        return [
            'total' => $reviews->count(),
            'approved' => $reviews->where('action', 'approved')->count(),
            'rejected' => $reviews->where('action', 'rejected')->count(),
            'pending' => $reviews->whereNull('completed_at')->count(),
            'expired' => $reviews->expired()->count(),
            'average_rating' => $reviews->whereNotNull('feedback_rating')->avg('feedback_rating') ?? 0,
            'average_review_time' => $reviews->whereNotNull('review_time_spent')->avg('review_time_spent') ?? 0,
        ];
    }

    public function handleExpiredReviews(): int
    {
        $expiredReviews = $this->findExpiredReviews();
        $count = 0;

        foreach ($expiredReviews as $review) {
            $review->handleExpiration();
            $count++;
        }

        return $count;
    }

    public function getAvailableReviewsForUser(User $user, Project $project): Collection
    {
        return Review::getAvailableReviewsForUser($user->id, $project->id);
    }
}
