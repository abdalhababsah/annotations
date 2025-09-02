<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\BatchRepositoryInterface;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function __construct(
        private BatchRepositoryInterface $batchRepository,
        private AudioFileRepositoryInterface $audioFileRepository
    ) {}

    /**
     * Create a new batch with validation
     */
    public function createBatch(Project $project, array $data, User $creator): Batch
    {
        $batch = $this->batchRepository->create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);

        return $batch;
    }

    /**
     * Create batch with initial tasks from audio files
     */
    public function createBatchWithTasks(Project $project, array $batchData, array $audioFileIds, User $creator): Batch
    {
        if (empty($audioFileIds)) {
            throw new \Exception('At least one audio file must be selected to create tasks.');
        }

        $validAudioFiles = $project->audioFiles()->whereIn('id', $audioFileIds)->pluck('id')->toArray();
        if (empty($validAudioFiles)) {
            throw new \Exception('No valid audio files found for this project.');
        }

        return $this->batchRepository->createWithTasks($project, $batchData, $validAudioFiles, $creator);
    }

    /**
     * (Legacy) Add tasks – returns count only. Kept for backward compatibility.
     */
    public function addTasksToBatch(Batch $batch, array $audioFileIds): int
    {
        $result = $this->addTasksToBatchDetailed($batch, $audioFileIds);
        return $result['added'] ?? 0;
    }

    /**
     * NEW: Add tasks with detailed outcome (added/duplicates/invalid/already-in-batch).
     */
    public function addTasksToBatchDetailed(Batch $batch, array $audioFileIds): array
    {
        if (!$batch->isDraft()) {
            throw new \Exception('Tasks can only be added to draft batches.');
        }
        if (empty($audioFileIds)) {
            throw new \Exception('No audio files selected.');
        }

        // Normalize & find duplicates in request
        $audioFileIds = array_values(array_map('intval', $audioFileIds));
        $seen = [];
        $duplicatesInRequest = [];
        foreach ($audioFileIds as $id) {
            if (isset($seen[$id])) $duplicatesInRequest[] = $id;
            $seen[$id] = true;
        }
        $uniqueIds = array_values(array_unique($audioFileIds));

        // Valid IDs that belong to the same project
        $validIds = $batch->project->audioFiles()
            ->whereIn('id', $uniqueIds)
            ->pluck('id')
            ->toArray();

        // Invalid for this project (or non-existent)
        $invalidForProject = array_values(array_diff($uniqueIds, $validIds));

        // Already present in this batch
        $alreadyInBatch = $batch->tasks()
            ->whereIn('audio_file_id', $validIds)
            ->pluck('audio_file_id')
            ->toArray();

        // Insert = valid - already in batch
        $toInsert = array_values(array_diff($validIds, $alreadyInBatch));

        $createdTaskIds = [];
        $addedCount = 0;

        DB::transaction(function () use ($batch, $toInsert, &$createdTaskIds, &$addedCount) {
            foreach ($toInsert as $audioId) {
                $task = $batch->tasks()->create([
                    'project_id'    => $batch->project_id,
                    'audio_file_id' => $audioId,
                    'status'        => 'pending',
                ]);
                $createdTaskIds[] = $task->id;
                $addedCount++;
            }
        });

        // update batch cached stats
        $this->updateBatchStatistics($batch);

        return [
            'added'                    => $addedCount,
            'created_task_ids'         => $createdTaskIds,
            'skipped_already_in_batch' => $alreadyInBatch,
            'invalid_for_project'      => $invalidForProject,
            'duplicates_in_request'    => $duplicatesInRequest,
        ];
    }

    /**
     * Remove task from batch
     */
    public function removeTaskFromBatch(Batch $batch, int $taskId): bool
    {
        if (!$batch->isDraft()) {
            throw new \Exception('Tasks can only be removed from draft batches.');
        }

        $task = $batch->tasks()->find($taskId);
        if (!$task) {
            throw new \Exception('Task not found in this batch.');
        }

        // Allow removing both fresh draft tasks and pending tasks
        if (!in_array($task->status, ['draft', 'pending'], true)) {
            throw new \Exception('Only draft or pending tasks can be removed from the batch.');
        }

        $deleted = (bool) $task->delete();

        // update batch cached stats
        $this->updateBatchStatistics($batch);

        return $deleted;
    }

    /**
     * Publish batch and make tasks available
     */
    public function publishBatch(Batch $batch): Batch
    {
        if (!$batch->canBePublished()) {
            throw new \Exception('Batch cannot be published. Ensure it has at least one task and is in draft status.');
        }

        return DB::transaction(function () use ($batch) {
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
            throw new \Exception('Batch cannot be paused in its current state.');
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

        $newStatus = $batch->total_tasks > 0 && $batch->completed_tasks >= $batch->total_tasks
            ? 'completed'
            : ($batch->total_tasks > 0 ? 'published' : 'draft');

        $batch->update([
            'status' => $newStatus,
            'paused_at' => null,
        ]);

        return $batch;
    }

    public function updateBatch(Batch $batch, array $data): Batch
    {
        if (!$batch->isDraft()) {
            throw new \Exception('Only draft batches can be edited.');
        }

        $batch->update([
            'name' => $data['name'] ?? $batch->name,
            'description' => $data['description'] ?? $batch->description,
        ]);

        return $batch;
    }

    public function deleteBatch(Batch $batch): bool
    {
        if (!$batch->canBeDeleted()) {
            throw new \Exception('Batch cannot be deleted in its current state. Only draft and completed batches can be deleted.');
        }

        return $this->batchRepository->deleteBatchWithTasks($batch);
    }

    public function duplicateBatch(Batch $batch, User $creator, string $newName = null): Batch
    {
        $newName = $newName ?? $batch->name . ' (Copy)';
        return $this->batchRepository->duplicateBatch($batch, $creator, $newName);
    }

    public function getBatchProgress(Batch $batch): array
    {
        $taskStats = $batch->tasks()
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = "pending" THEN 1 END) as pending,
                COUNT(CASE WHEN status = "assigned" THEN 1 END) as assigned,
                COUNT(CASE WHEN status = "in_progress" THEN 1 END) as in_progress,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed,
                COUNT(CASE WHEN status = "under_review" THEN 1 END) as under_review,
                COUNT(CASE WHEN status = "approved" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected
            ')
            ->first();

        return [
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'batch_status' => $batch->status,
            'total_tasks' => $taskStats->total,
            'pending_tasks' => $taskStats->pending,
            'assigned_tasks' => $taskStats->assigned,
            'in_progress_tasks' => $taskStats->in_progress,
            'completed_tasks' => $taskStats->completed,
            'under_review_tasks' => $taskStats->under_review,
            'approved_tasks' => $taskStats->approved,
            'rejected_tasks' => $taskStats->rejected,
            'completion_percentage' => $taskStats->total > 0
                ? round((($taskStats->approved + $taskStats->rejected) / $taskStats->total) * 100, 2)
                : 0,
            'published_at' => $batch->published_at?->format('Y-m-d H:i'),
            'completed_at' => $batch->completed_at?->format('Y-m-d H:i'),
            'can_be_published' => $batch->canBePublished(),
            'can_be_paused' => $batch->canBePaused(),
            'can_be_resumed' => $batch->canBeResumed(),
            'can_be_deleted' => $batch->canBeDeleted(),
        ];
    }

    public function getAvailableAudioFiles(Batch $batch): \Illuminate\Database\Eloquent\Collection
    {
        return $batch->project->audioFiles()
            ->whereDoesntHave('tasks', function ($query) use ($batch) {
                $query->where('batch_id', $batch->id);
            })
            ->orderBy('original_filename')
            ->get();
    }

    public function getProjectBatchStatistics(Project $project): array
    {
        return $this->batchRepository->getProjectBatchStatistics($project);
    }

    public function getBatchesReadyForWork(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->batchRepository->getBatchesReadyForWork();
    }

    public function getUserAvailableBatches(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $this->batchRepository->getUserAvailableBatches($user);
    }

    public function updateBatchStatistics(Batch $batch): Batch
    {
        return $this->batchRepository->updateBatchStatistics($batch);
    }

    public function getNextTaskFromBatch(Batch $batch, int $userId): ?\App\Models\Task
    {
        if (!in_array($batch->status, ['published', 'in_progress'])) {
            return null;
        }

        return $batch->getAvailableTasksForUser($userId)->first();
    }

    public function canAssignTasks(Batch $batch): bool
    {
        return in_array($batch->status, ['published', 'in_progress']) &&
            $batch->total_tasks > $batch->completed_tasks;
    }

    public function transformBatchForResponse(Batch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'description' => $batch->description,
            'status' => $batch->status,
            'total_tasks' => $batch->total_tasks,
            'completed_tasks' => $batch->completed_tasks,
            'approved_tasks' => $batch->approved_tasks,
            'rejected_tasks' => $batch->rejected_tasks,
            'completion_percentage' => $batch->completion_percentage,
            'created_at' => $batch->created_at->format('Y-m-d H:i'),
            'published_at' => $batch->published_at?->format('Y-m-d H:i'),
            'paused_at' => $batch->paused_at?->format('Y-m-d H:i'),
            'completed_at' => $batch->completed_at?->format('Y-m-d H:i'),
            'creator' => [
                'id' => $batch->creator->id,
                'name' => $batch->creator->full_name,
            ],
            'project' => [
                'id' => $batch->project->id,
                'name' => $batch->project->name,
            ],
            'actions' => [
                'can_publish' => $batch->canBePublished(),
                'can_pause' => $batch->canBePaused(),
                'can_resume' => $batch->canBeResumed(),
                'can_delete' => $batch->canBeDeleted(),
                'can_edit' => $batch->isDraft(),
            ],
        ];
    }
}
