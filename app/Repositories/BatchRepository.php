<?php
// BatchRepository.php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\BatchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BatchRepository extends BaseRepository implements BatchRepositoryInterface
{
    public function __construct(Batch $model)
    {
        parent::__construct($model);
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['creator', 'tasks'])
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    public function findByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'creator'])
                          ->get();
    }

    public function findPublishedBatches(): Collection
    {
        return $this->model->whereIn('status', ['published', 'in_progress'])
                          ->with(['project', 'tasks'])
                          ->get();
    }

    public function paginateByProject(Project $project, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('project_id', $project->id)
                            ->with(['creator', 'tasks']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = ['created_at', 'updated_at', 'name', 'status'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function createWithTasks(Project $project, array $batchData, array $audioFileIds, User $creator): Batch
    {
        return \DB::transaction(function () use ($project, $batchData, $audioFileIds, $creator) {
            // Create batch
            $batch = $this->create([
                'project_id' => $project->id,
                'name' => $batchData['name'],
                'description' => $batchData['description'] ?? null,
                'created_by' => $creator->id,
                'status' => 'draft',
            ]);

            // Create tasks from audio files
            foreach ($audioFileIds as $audioFileId) {
                $audioFile = $project->audioFiles()->find($audioFileId);
                if ($audioFile) {
                    $batch->tasks()->create([
                        'project_id' => $project->id,
                        'audio_file_id' => $audioFile->id,
                        'status' => 'pending',
                    ]);
                }
            }

            return $batch->load(['tasks', 'creator']);
        });
    }

    public function publishBatch(Batch $batch): Batch
    {
        if (!$batch->canBePublished()) {
            throw new \Exception('Batch cannot be published. Ensure it has at least one task.');
        }

        return \DB::transaction(function () use ($batch) {
            $batch->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return $batch->fresh(['tasks']);
        });
    }

    public function pauseBatch(Batch $batch): Batch
    {
        if (!$batch->canBePaused()) {
            throw new \Exception('Batch cannot be paused.');
        }

        $batch->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $batch;
    }

    public function resumeBatch(Batch $batch): Batch
    {
        if (!$batch->canBeResumed()) {
            throw new \Exception('Batch cannot be resumed.');
        }

        $newStatus = $batch->total_tasks === $batch->completed_tasks ? 'completed' : 'published';

        $batch->update([
            'status' => $newStatus,
            'paused_at' => null,
        ]);

        return $batch;
    }

    public function getProjectBatchStatistics(Project $project): array
    {
        $batches = $this->model->where('project_id', $project->id);

        return [
            'total_batches' => $batches->count(),
            'draft_batches' => $batches->where('status', 'draft')->count(),
            'published_batches' => $batches->where('status', 'published')->count(),
            'in_progress_batches' => $batches->where('status', 'in_progress')->count(),
            'completed_batches' => $batches->where('status', 'completed')->count(),
            'paused_batches' => $batches->where('status', 'paused')->count(),
            'total_tasks' => $batches->sum('total_tasks'),
            'completed_tasks' => $batches->sum('completed_tasks'),
            'approved_tasks' => $batches->sum('approved_tasks'),
            'rejected_tasks' => $batches->sum('rejected_tasks'),
            'average_completion' => $batches->avg('completion_percentage') ?? 0,
        ];
    }

    public function getBatchesReadyForWork(): Collection
    {
        return $this->model->whereIn('status', ['published', 'in_progress'])
                          ->where('total_tasks', '>', \DB::raw('completed_tasks'))
                          ->with(['project', 'tasks' => function ($query) {
                              $query->where('status', 'pending');
                          }])
                          ->get();
    }

    public function getUserAvailableBatches(User $user): Collection
    {
        // Get projects user is a member of
        $projectIds = $user->projects()->pluck('projects.id');

        return $this->model->whereIn('project_id', $projectIds)
                          ->whereIn('status', ['published', 'in_progress'])
                          ->where('total_tasks', '>', \DB::raw('completed_tasks'))
                          ->with(['project', 'tasks' => function ($query) use ($user) {
                              $query->where('status', 'pending')
                                   ->whereNotIn('id', function ($subQuery) use ($user) {
                                       $subQuery->select('task_id')
                                               ->from('skip_activities')
                                               ->where('user_id', $user->id)
                                               ->where('activity_type', 'task');
                                   });
                          }])
                          ->get();
    }

    public function updateBatchStatistics(Batch $batch): Batch
    {
        $taskStats = $batch->tasks()
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ("completed", "approved", "rejected") THEN 1 END) as completed,
                COUNT(CASE WHEN status = "approved" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected
            ')
            ->first();

        $completionPercentage = $taskStats->total > 0
            ? round(($taskStats->completed / $taskStats->total) * 100, 2)
            : 0;

        $batch->update([
            'total_tasks' => $taskStats->total,
            'completed_tasks' => $taskStats->completed,
            'approved_tasks' => $taskStats->approved,
            'rejected_tasks' => $taskStats->rejected,
            'completion_percentage' => $completionPercentage,
        ]);

        // Auto-complete batch if all tasks are done and batch is in progress
        if ($batch->status === 'in_progress' && $taskStats->total > 0 && $taskStats->completed >= $taskStats->total) {
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return $batch->fresh();
    }

    public function duplicateBatch(Batch $originalBatch, User $creator, string $newName = null): Batch
    {
        return \DB::transaction(function () use ($originalBatch, $creator, $newName) {
            // Create new batch
            $newBatch = $this->create([
                'project_id' => $originalBatch->project_id,
                'name' => $newName ?? $originalBatch->name . ' (Copy)',
                'description' => $originalBatch->description,
                'created_by' => $creator->id,
                'status' => 'draft',
            ]);

            // Copy tasks from original batch
            foreach ($originalBatch->tasks as $task) {
                $newBatch->tasks()->create([
                    'project_id' => $originalBatch->project_id,
                    'audio_file_id' => $task->audio_file_id,
                    'status' => 'draft',
                ]);
            }

            return $newBatch->load(['tasks', 'creator']);
        });
    }

    public function deleteBatchWithTasks(Batch $batch): bool
    {
        if (!$batch->canBeDeleted()) {
            throw new \Exception('Batch cannot be deleted in its current state.');
        }

        return \DB::transaction(function () use ($batch) {
            // Delete all tasks
            $batch->tasks()->delete();

            // Delete batch
            return $batch->delete();
        });
    }
}
