<?php
// AnnotationRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\Annotation;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface AnnotationRepositoryInterface extends BaseRepositoryInterface
{
    public function findByTask(Task $task): Collection;
    public function findByAnnotator(User $annotator): Collection;
    public function findPendingReview(): Collection;
    public function findByProject(Project $project): Collection;
    public function createWithValues(Task $task, User $annotator, array $data): Annotation;
    public function updateWithValues(Annotation $annotation, array $data): Annotation;
    public function getAnnotationProgress(Task $task): array;
    public function submitForReview(Annotation $annotation): Annotation;
    public function getAnnotationsReadyForReview(Project $project): Collection;
    public function getUserAnnotationHistory(User $user, Project $project): Collection;
    public function getProjectAnnotationSummary(Project $project): array;
}