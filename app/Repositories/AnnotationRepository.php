<?php
// AnnotationRepository.php

namespace App\Repositories;

use App\Models\Annotation;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Repositories\Contracts\AnnotationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AnnotationRepository extends BaseRepository implements AnnotationRepositoryInterface
{
    public function __construct(Annotation $model)
    {
        parent::__construct($model);
    }

    public function findByTask(Task $task): Collection
    {
        return $this->model->where('task_id', $task->id)
            ->with(['annotationValues.dimension', 'annotator'])
            ->get();
    }

    public function findByAnnotator(User $annotator): Collection
    {
        return $this->model->where('annotator_id', $annotator->id)
            ->with(['task.project', 'task.audioFile', 'annotationValues'])
            ->get();
    }

    public function findPendingReview(): Collection
    {
        return $this->model->where('status', 'submitted')
            ->with(['task.project', 'annotator', 'annotationValues'])
            ->get();
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->whereHas('task', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })
            ->with(['task.audioFile', 'annotator', 'annotationValues', 'reviews'])
            ->get();
    }

    public function createWithValues(Task $task, User $annotator, array $data): Annotation
    {
        return DB::transaction(function () use ($task, $annotator, $data) {
            // Create annotation
            $annotation = $this->create([
                'task_id' => $task->id,
                'annotator_id' => $annotator->id,
                'status' => 'draft',
                'started_at' => now()
            ]);

            // Create annotation values
            if (isset($data['values'])) {
                foreach ($data['values'] as $dimensionId => $valueData) {
                    $annotation->annotationValues()->create([
                        'dimension_id' => $dimensionId,
                        'selected_value' => $valueData['selected_value'] ?? null,
                        'numeric_value' => $valueData['numeric_value'] ?? null,
                        'notes' => $valueData['notes'] ?? null
                    ]);
                }
            }

            // Update task status
            $task->update(['status' => 'in_progress', 'started_at' => now()]);

            return $annotation->load(['annotationValues.dimension', 'task']);
        });
    }

    public function updateWithValues(Annotation $annotation, array $data): Annotation
    {
        return DB::transaction(function () use ($annotation, $data) {
            // Update annotation
            $annotation->update([
                'total_time_spent' => $data['total_time_spent'] ?? $annotation->total_time_spent,
                'updated_at' => now()
            ]);

            // Update annotation values
            if (isset($data['values'])) {
                foreach ($data['values'] as $dimensionId => $valueData) {
                    $annotation->annotationValues()->updateOrCreate(
                        ['dimension_id' => $dimensionId],
                        [
                            'selected_value' => $valueData['selected_value'] ?? null,
                            'numeric_value' => $valueData['numeric_value'] ?? null,
                            'notes' => $valueData['notes'] ?? null
                        ]
                    );
                }
            }

            return $annotation->fresh(['annotationValues.dimension']);
        });
    }

    public function getAnnotationProgress(Task $task): array
    {
        $annotation = $this->model->where('task_id', $task->id)->first();

        if (!$annotation) {
            return ['status' => 'not_started', 'completion_percentage' => 0];
        }

        $totalDimensions = $task->project->annotationDimensions()->where('is_required', true)->count();
        $completedDimensions = $annotation->annotationValues()->count();

        $completionPercentage = $totalDimensions > 0 ? ($completedDimensions / $totalDimensions) * 100 : 0;

        return [
            'status' => $annotation->status,
            'completion_percentage' => round($completionPercentage, 2),
            'completed_dimensions' => $completedDimensions,
            'total_dimensions' => $totalDimensions,
            'time_spent' => $annotation->total_time_spent ?? 0
        ];
    }

    public function submitForReview(Annotation $annotation): Annotation
    {
        return DB::transaction(function () use ($annotation) {
            $annotation->update([
                'status' => 'submitted',
                'submitted_at' => now()
            ]);

            // Update task status
            $annotation->task->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $annotation->fresh(['task']);
        });
    }

    public function getAnnotationsReadyForReview(Project $project): Collection
    {
        return $this->model->whereHas('task', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })
            ->where('status', 'submitted')
            ->with(['task.audioFile', 'annotator', 'annotationValues.dimension'])
            ->get();
    }

    public function getUserAnnotationHistory(User $user, Project $project): Collection
    {
        return $this->model->whereHas('task', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })
            ->where('annotator_id', $user->id)
            ->with(['task.audioFile', 'annotationValues', 'reviews'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getProjectAnnotationSummary(Project $project): array
    {
        $annotations = $this->model->whereHas('task', function($query) use ($project) {
            $query->where('project_id', $project->id);
        });

        return [
            'total' => $annotations->count(),
            'draft' => $annotations->where('status', 'draft')->count(),
            'submitted' => $annotations->where('status', 'submitted')->count(),
            'under_review' => $annotations->where('status', 'under_review')->count(),
            'approved' => $annotations->where('status', 'approved')->count(),
            'rejected' => $annotations->where('status', 'rejected')->count(),
            'average_time_spent' => $annotations->whereNotNull('total_time_spent')->avg('total_time_spent') ?? 0,
        ];
    }
}
