<?php
// DimensionValueRepositoryInterface.php - FIXED

namespace App\Repositories\Contracts;

use App\Models\DimensionValue;
use App\Models\AnnotationDimension;
use Illuminate\Database\Eloquent\Collection;

interface DimensionValueRepositoryInterface extends BaseRepositoryInterface
{
    public function findByDimension(AnnotationDimension $dimension): Collection;
    public function createForDimension(AnnotationDimension $dimension, array $values): Collection;
    public function updateOrderForDimension(AnnotationDimension $dimension, array $orderedIds): void;
}