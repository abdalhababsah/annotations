<?php
// AudioFileRepository.php

namespace App\Repositories;

use App\Models\AudioFile;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class AudioFileRepository extends BaseRepository implements AudioFileRepositoryInterface
{
    public function __construct(AudioFile $model)
    {
        parent::__construct($model);
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['uploader', 'tasks'])
                          ->get();
    }

    public function findByUploader(User $uploader): Collection
    {
        return $this->model->where('uploaded_by', $uploader->id)
                          ->with(['project'])
                          ->get();
    }

    public function createFromUpload(array $data, string $filePath): AudioFile
    {
        $fileData = array_merge($data, [
            'file_path' => $filePath,
            'uploaded_by' => auth()->id(),
        ]);

        return $this->create($fileData);
    }

    public function deleteWithFile(AudioFile $audioFile): bool
    {
        // Delete physical file
        if (Storage::exists($audioFile->file_path)) {
            Storage::delete($audioFile->file_path);
        }

        // Delete database record
        return $audioFile->delete();
    }

    public function getProjectAudioStatistics(Project $project): array
    {
        $audioFiles = $this->model->where('project_id', $project->id);

        return [
            'total_files' => $audioFiles->count(),
            'total_duration' => $audioFiles->sum('duration') ?? 0,
            'total_size' => $audioFiles->sum('file_size') ?? 0,
            'average_duration' => $audioFiles->avg('duration') ?? 0,
            'files_with_tasks' => $audioFiles->whereHas('tasks')->count(),
            'files_without_tasks' => $audioFiles->whereDoesntHave('tasks')->count(),
        ];
    }

    public function getFilesWithoutTasks(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->whereDoesntHave('tasks')
                          ->get();
    }

    public function bulkCreateTasks(Project $project, array $audioFileIds): Collection
    {
        $tasks = collect();
        
        foreach ($audioFileIds as $audioFileId) {
            $audioFile = $this->find($audioFileId);
            if ($audioFile && $audioFile->project_id === $project->id) {
                // Check if task already exists
                if (!$audioFile->tasks()->exists()) {
                    $task = $audioFile->tasks()->create([
                        'project_id' => $project->id,
                        'status' => 'pending',
                    ]);
                    $tasks->push($task);
                }
            }
        }

        return $tasks;
    }
}
