<?php
// ReviewRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\Review;
use App\Models\Annotation;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function findByAnnotation(Annotation $annotation): Collection;
    public function findByReviewer(User $reviewer): Collection;
    public function findActiveReviews(): Collection;
    public function findExpiredReviews(): Collection;
    public function createReview(Annotation $annotation, User $reviewer): Review;
    public function completeReview(Review $review, array $data): Review;
    public function getUserReviewHistory(User $user, Project $project): Collection;
    public function getProjectReviewSummary(Project $project): array;
    public function handleExpiredReviews(): int;
    public function getAvailableReviewsForUser(User $user, Project $project): Collection;
}
