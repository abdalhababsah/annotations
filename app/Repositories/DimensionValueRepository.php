<?php
// DimensionValueRepository.php

namespace App\Repositories;

use App\Models\DimensionValue;
use App\Models\AnnotationDimension;
use App\Repositories\Contracts\DimensionValueRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DimensionValueRepository extends BaseRepository implements DimensionValueRepositoryInterface
{
    public function __construct(DimensionValue $model)
    {
        parent::__construct($model);
    }

    public function findByDimension(AnnotationDimension $dimension): Collection
    {
        return $this->model->where('dimension_id', $dimension->id)
                          ->orderBy('display_order')
                          ->get();
    }

    public function createForDimension(AnnotationDimension $dimension, array $values): Collection
    {
        $createdValues = collect();
        
        foreach ($values as $index => $valueData) {
            $value = $this->create([
                'dimension_id' => $dimension->id,
                'value' => $valueData['value'],
                'label' => $valueData['label'] ?? $valueData['value'],
                'display_order' => $valueData['display_order'] ?? $index,
            ]);
            $createdValues->push($value);
        }

        return $createdValues;
    }

    public function updateOrderForDimension(AnnotationDimension $dimension, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $this->model->where('id', $id)
                       ->where('dimension_id', $dimension->id)
                       ->update(['display_order' => $index]);
        }
    }
}
