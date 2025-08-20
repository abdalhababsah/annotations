<?php

namespace App\Repositories;

use App\Models\Annotation;
use App\Models\Task;
use App\Models\User;
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
                          ->with(['values.dimension', 'annotator', 'comments'])
                          ->get();
    }

    public function findByAnnotator(User $annotator): Collection
    {
        return $this->model->where('annotator_id', $annotator->id)
                          ->with(['task.project', 'task.mediaFile', 'values'])
                          ->get();
    }

    public function findPendingReview(): Collection
    {
        return $this->model->where('status', 'submitted')
                          ->with(['task.project', 'annotator', 'values'])
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
                    $annotation->values()->create([
                        'dimension_id' => $dimensionId,
                        'value_numeric' => $valueData['value_numeric'] ?? null,
                        'value_text' => $valueData['value_text'] ?? null,
                        'value_boolean' => $valueData['value_boolean'] ?? null,
                        'value_categorical' => $valueData['value_categorical'] ?? null,
                        'value_form_data' => $valueData['value_form_data'] ?? null,
                        'confidence_score' => $valueData['confidence_score'] ?? null,
                        'notes' => $valueData['notes'] ?? null
                    ]);
                }
            }

            // Update task status
            $task->update(['status' => 'in_progress', 'started_at' => now()]);

            return $annotation->load(['values.dimension', 'task']);
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
                    $annotation->values()->updateOrCreate(
                        ['dimension_id' => $dimensionId],
                        [
                            'value_numeric' => $valueData['value_numeric'] ?? null,
                            'value_text' => $valueData['value_text'] ?? null,
                            'value_boolean' => $valueData['value_boolean'] ?? null,
                            'value_categorical' => $valueData['value_categorical'] ?? null,
                            'value_form_data' => $valueData['value_form_data'] ?? null,
                            'confidence_score' => $valueData['confidence_score'] ?? null,
                            'notes' => $valueData['notes'] ?? null
                        ]
                    );
                }
            }

            return $annotation->fresh(['values.dimension']);
        });
    }

    public function getAnnotationProgress(Task $task): array
    {
        $annotation = $this->model->where('task_id', $task->id)->first();
        
        if (!$annotation) {
            return ['status' => 'not_started', 'completion_percentage' => 0];
        }

        $totalDimensions = $task->project->annotationDimensions()->where('is_required', true)->count();
        $completedDimensions = $annotation->values()->count();
        
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
                'actual_duration' => $annotation->total_time_spent
            ]);

            return $annotation->fresh(['task']);
        });
    }
}
