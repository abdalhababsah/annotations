<?php
// BatchService.php

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


        // Create batch
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

        // Validate audio files belong to project
        $validAudioFiles = $project->audioFiles()->whereIn('id', $audioFileIds)->pluck('id')->toArray();
        
        if (empty($validAudioFiles)) {
            throw new \Exception('No valid audio files found for this project.');
        }

        return $this->batchRepository->createWithTasks($project, $batchData, $validAudioFiles, $creator);
    }

    /**
     * Add tasks to existing batch
     */
    public function addTasksToBatch(Batch $batch, array $audioFileIds): int
    {
        if (!$batch->isDraft()) {
            throw new \Exception('Tasks can only be added to draft batches.');
        }

        if (empty($audioFileIds)) {
            throw new \Exception('No audio files selected.');
        }

        $addedCount = 0;

        DB::transaction(function () use ($batch, $audioFileIds, &$addedCount) {
            foreach ($audioFileIds as $audioFileId) {
                $audioFile = $batch->project->audioFiles()->find($audioFileId);
                
                // Check if task already exists for this audio file in this batch
                if ($audioFile && !$batch->tasks()->where('audio_file_id', $audioFileId)->exists()) {
                    $batch->tasks()->create([
                        'project_id' => $batch->project_id,
                        'audio_file_id' => $audioFileId,
                        'status' => 'draft',
                    ]);
                    $addedCount++;
                }
            }
        });

        return $addedCount;
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

        if ($task->status !== 'draft') {
            throw new \Exception('Only draft tasks can be removed from batch.');
        }

        return $task->delete();
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
            // Update batch status
            $batch->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            // Update all draft tasks to pending
            $batch->tasks()->where('status', 'draft')->update(['status' => 'pending']);

            return $batch->fresh(['tasks']);
        });
    }

    /**
     * Pause batch to stop new task assignments
     */
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

    /**
     * Resume paused batch
     */
    public function resumeBatch(Batch $batch): Batch
    {
        if (!$batch->canBeResumed()) {
            throw new \Exception('Batch cannot be resumed.');
        }

        // Determine new status based on completion
        $newStatus = $batch->total_tasks > 0 && $batch->completed_tasks >= $batch->total_tasks 
            ? 'completed' 
            : ($batch->total_tasks > 0 ? 'published' : 'draft');

        $batch->update([
            'status' => $newStatus,
            'paused_at' => null,
        ]);

        return $batch;
    }

    /**
     * Update batch information
     */
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

    /**
     * Delete batch and all its tasks
     */
    public function deleteBatch(Batch $batch): bool
    {
        if (!$batch->canBeDeleted()) {
            throw new \Exception('Batch cannot be deleted in its current state. Only draft and completed batches can be deleted.');
        }

        return $this->batchRepository->deleteBatchWithTasks($batch);
    }

    /**
     * Duplicate batch with all its tasks
     */
    public function duplicateBatch(Batch $batch, User $creator, string $newName = null): Batch
    {
        $newName = $newName ?? $batch->name . ' (Copy)';
        
        return $this->batchRepository->duplicateBatch($batch, $creator, $newName);
    }

    /**
     * Get batch progress and statistics
     */
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

    /**
     * Get available audio files for batch (not already in tasks)
     */
    public function getAvailableAudioFiles(Batch $batch): \Illuminate\Database\Eloquent\Collection
    {
        return $batch->project->audioFiles()
            ->whereDoesntHave('tasks', function ($query) use ($batch) {
                $query->where('batch_id', $batch->id);
            })
            ->orderBy('original_filename')
            ->get();
    }

    /**
     * Get project batch statistics
     */
    public function getProjectBatchStatistics(Project $project): array
    {
        return $this->batchRepository->getProjectBatchStatistics($project);
    }

    /**
     * Get batches ready for work assignment
     */
    public function getBatchesReadyForWork(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->batchRepository->getBatchesReadyForWork();
    }

    /**
     * Get available batches for a user
     */
    public function getUserAvailableBatches(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $this->batchRepository->getUserAvailableBatches($user);
    }

    /**
     * Auto-update batch statistics (called by task model events)
     */
    public function updateBatchStatistics(Batch $batch): Batch
    {
        return $this->batchRepository->updateBatchStatistics($batch);
    }

    /**
     * Get next available task from batch for user
     */
    public function getNextTaskFromBatch(Batch $batch, int $userId): ?\App\Models\Task
    {
        if (!in_array($batch->status, ['published', 'in_progress'])) {
            return null;
        }

        return $batch->getAvailableTasksForUser($userId)->first();
    }

    /**
     * Check if batch can accept new task assignments
     */
    public function canAssignTasks(Batch $batch): bool
    {
        return in_array($batch->status, ['published', 'in_progress']) && 
               $batch->total_tasks > $batch->completed_tasks;
    }

    /**
     * Transform batch for API/frontend response
     */
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