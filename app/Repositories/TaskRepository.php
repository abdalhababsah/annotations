<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\MediaFile;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['mediaFile', 'assignee', 'annotations'])
                          ->get();
    }

    public function findByAssignee(User $user): Collection
    {
        return $this->model->where('assigned_to', $user->id)
                          ->with(['project', 'mediaFile', 'annotations'])
                          ->get();
    }

    public function findPendingTasks(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'mediaFile'])
                          ->get();
    }

    public function findOverdueTasks(): Collection
    {
        return $this->model->where('due_date', '<', now())
                          ->whereNotIn('status', ['completed', 'approved'])
                          ->with(['project', 'assignee', 'mediaFile'])
                          ->get();
    }

    public function assignTask(Task $task, User $user): Task
    {
        $task->update([
            'assigned_to' => $user->id,
            'status' => 'assigned',
            'assigned_at' => now()
        ]);

        return $task->fresh(['assignee', 'project']);
    }

    public function getTasksWithAnnotations(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['annotations.values', 'mediaFile', 'assignee'])
                          ->get();
    }

    public function createFromMediaFile(array $data): Task
    {
        $mediaFile = MediaFile::findOrFail($data['media_file_id']);
        
        $taskData = array_merge($data, [
            'project_id' => $mediaFile->project_id,
            'task_name' => $data['task_name'] ?? "Annotate {$mediaFile->original_filename}",
            'estimated_duration' => $this->getEstimatedDuration($mediaFile),
            'due_date' => $data['due_date'] ?? now()->addDays(7)
        ]);

        return $this->create($taskData);
    }

    public function getUserWorkload(User $user, Project $project): int
    {
        return $this->model->where('assigned_to', $user->id)
                          ->where('project_id', $project->id)
                          ->whereIn('status', ['assigned', 'in_progress'])
                          ->count();
    }

    private function getEstimatedDuration(MediaFile $mediaFile): int
    {
        // Estimate duration based on media type and file properties
        switch ($mediaFile->media_type) {
            case 'audio':
                // For audio: duration in seconds / 60 + 15 minutes for annotation
                return ($mediaFile->duration ? ceil($mediaFile->duration / 60) : 30) + 15;
            case 'image':
                // For images: 10-15 minutes depending on complexity
                return 15;
            default:
                return 30;
        }
    }
}
