<?php
// AnnotationDimensionRepositoryInterface.php - BONUS Contract

namespace App\Repositories\Contracts;

use App\Models\AnnotationDimension;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface AnnotationDimensionRepositoryInterface extends BaseRepositoryInterface
{
    public function findByProject(Project $project): Collection;
    public function createWithValues(Project $project, array $data): AnnotationDimension;
    public function updateWithValues(AnnotationDimension $dimension, array $data): AnnotationDimension;
    public function reorderDimensions(Project $project, array $orderedIds): void;
    public function getDimensionStatistics(AnnotationDimension $dimension): array;
}