<?php
// BatchRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Batch;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BatchRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProject(Project $project): Collection;
    
    public function findByStatus(string $status): Collection;
    
    public function findPublishedBatches(): Collection;
    
    public function paginateByProject(Project $project, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    
    public function createWithTasks(Project $project, array $batchData, array $audioFileIds, User $creator): Batch;
    
    public function publishBatch(Batch $batch): Batch;
    
    public function pauseBatch(Batch $batch): Batch;
    
    public function resumeBatch(Batch $batch): Batch;
    
    public function getProjectBatchStatistics(Project $project): array;
    
    public function getBatchesReadyForWork(): Collection;
    
    public function getUserAvailableBatches(User $user): Collection;
    
    public function updateBatchStatistics(Batch $batch): Batch;
    
    public function duplicateBatch(Batch $originalBatch, User $creator, string $newName = null): Batch;
    
    public function deleteBatchWithTasks(Batch $batch): bool;
}