<?php
// AudioFileRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\AudioFile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AudioFileRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProject(Project $project): Collection;
    public function findByUploader(User $uploader): Collection;
    public function createFromUpload(array $data, string $filePath): AudioFile;
    public function deleteWithFile(AudioFile $audioFile): bool;
    public function getProjectAudioStatistics(Project $project): array;
    public function getFilesWithoutTasks(Project $project): Collection;
    public function bulkCreateTasks(Project $project, array $audioFileIds): Collection;
}