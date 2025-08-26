<?php
// TaskRepository.php

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\AudioFile;
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
                          ->with(['audioFile', 'assignee', 'annotations'])
                          ->get();
    }

    public function findByAssignee(User $user): Collection
    {
        return $this->model->where('assigned_to', $user->id)
                          ->with(['project', 'audioFile', 'annotations'])
                          ->get();
    }

    public function findPendingTasks(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'audioFile'])
                          ->get();
    }

    public function findOverdueTasks(): Collection
    {
        return $this->model->where('expires_at', '<', now())
                          ->whereIn('status', ['assigned', 'in_progress'])
                          ->with(['project', 'assignee', 'audioFile'])
                          ->get();
    }

    public function findExpiredTasks(): Collection
    {
        return $this->model->expired()->with(['project', 'assignee', 'audioFile'])->get();
    }

    public function assignTask(Task $task, User $user): Task
    {
        $task->update([
            'assigned_to' => $user->id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addMinutes($task->project->task_time_minutes),
        ]);

        return $task->fresh(['assignee', 'project']);
    }

    public function getTasksWithAnnotations(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['annotations.annotationValues', 'audioFile', 'assignee'])
                          ->get();
    }

    public function createFromAudioFile(array $data): Task
    {
        $audioFile = AudioFile::findOrFail($data['audio_file_id']);
        
        $taskData = [
            'project_id' => $audioFile->project_id,
            'audio_file_id' => $audioFile->id,
            'status' => 'pending',
        ];

        return $this->create($taskData);
    }

    public function createBulkFromAudioFiles(Project $project, array $audioFileIds): Collection
    {
        $tasks = collect();
        
        foreach ($audioFileIds as $audioFileId) {
            $task = $this->createFromAudioFile([
                'audio_file_id' => $audioFileId
            ]);
            $tasks->push($task);
        }

        return $tasks;
    }

    public function getUserWorkload(User $user, Project $project): int
    {
        return $this->model->where('assigned_to', $user->id)
                          ->where('project_id', $project->id)
                          ->whereIn('status', ['assigned', 'in_progress'])
                          ->count();
    }

    public function getAvailableTasksForUser(User $user, Project $project): Collection
    {
        return Task::getAvailableTasksForUser($user->id, $project->id);
    }

    public function handleExpiredTasks(): int
    {
        $expiredTasks = $this->findExpiredTasks();
        $count = 0;

        foreach ($expiredTasks as $task) {
            $task->handleExpiration();
            $count++;
        }

        return $count;
    }

    public function getUserTaskHistory(User $user, Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->where('assigned_to', $user->id)
                          ->with(['audioFile', 'annotations', 'skipActivities'])
                          ->orderBy('assigned_at', 'desc')
                          ->get();
    }

    public function getProjectTaskSummary(Project $project): array
    {
        $tasks = $this->model->where('project_id', $project->id);

        return [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'assigned' => $tasks->where('status', 'assigned')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'under_review' => $tasks->where('status', 'under_review')->count(),
            'approved' => $tasks->where('status', 'approved')->count(),
            'rejected' => $tasks->where('status', 'rejected')->count(),
            'expired_count' => $this->model->where('project_id', $project->id)->expired()->count(),
        ];
    }
}