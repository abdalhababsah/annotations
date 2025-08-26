<?php
// AnnotationDimensionRepository.php - BONUS

namespace App\Repositories;

use App\Models\AnnotationDimension;
use App\Models\Project;
use App\Repositories\Contracts\AnnotationDimensionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AnnotationDimensionRepository extends BaseRepository implements AnnotationDimensionRepositoryInterface
{
    public function __construct(AnnotationDimension $model)
    {
        parent::__construct($model);
    }

    public function findByProject(Project $project): Collection
    {
        return $this->model->where('project_id', $project->id)
                          ->with(['dimensionValues'])
                          ->orderBy('display_order')
                          ->get();
    }

    public function createWithValues(Project $project, array $data): AnnotationDimension
    {
        return DB::transaction(function () use ($project, $data) {
            // Create dimension
            $dimension = $this->create([
                'project_id' => $project->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'dimension_type' => $data['dimension_type'],
                'scale_min' => $data['scale_min'] ?? null,
                'scale_max' => $data['scale_max'] ?? null,
                'is_required' => $data['is_required'] ?? true,
                'display_order' => $data['display_order'] ?? 0,
            ]);

            // Create dimension values for categorical dimensions
            if ($dimension->dimension_type === 'categorical' && isset($data['values'])) {
                foreach ($data['values'] as $index => $valueData) {
                    $dimension->dimensionValues()->create([
                        'value' => $valueData['value'],
                        'label' => $valueData['label'] ?? $valueData['value'],
                        'display_order' => $index,
                    ]);
                }
            }

            return $dimension->load(['dimensionValues']);
        });
    }

    public function updateWithValues(AnnotationDimension $dimension, array $data): AnnotationDimension
    {
        return DB::transaction(function () use ($dimension, $data) {
            // Update dimension
            $dimension->update([
                'name' => $data['name'] ?? $dimension->name,
                'description' => $data['description'] ?? $dimension->description,
                'dimension_type' => $data['dimension_type'] ?? $dimension->dimension_type,
                'scale_min' => $data['scale_min'] ?? $dimension->scale_min,
                'scale_max' => $data['scale_max'] ?? $dimension->scale_max,
                'is_required' => $data['is_required'] ?? $dimension->is_required,
                'display_order' => $data['display_order'] ?? $dimension->display_order,
            ]);

            // Update dimension values if provided
            if (isset($data['values']) && is_array($data['values'])) {
                // Delete existing values
                $dimension->dimensionValues()->delete();
                
                // Create new values
                foreach ($data['values'] as $index => $valueData) {
                    $dimension->dimensionValues()->create([
                        'value' => $valueData['value'],
                        'label' => $valueData['label'] ?? $valueData['value'],
                        'display_order' => $index,
                    ]);
                }
            }

            return $dimension->fresh(['dimensionValues']);
        });
    }

    public function reorderDimensions(Project $project, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $this->model->where('id', $id)
                       ->where('project_id', $project->id)
                       ->update(['display_order' => $index]);
        }
    }

    public function getDimensionStatistics(AnnotationDimension $dimension): array
    {
        $annotationValues = $dimension->annotationValues();
        
        $statistics = [
            'total_responses' => $annotationValues->count(),
            'dimension_type' => $dimension->dimension_type,
        ];

        if ($dimension->dimension_type === 'categorical') {
            $statistics['value_distribution'] = $annotationValues
                ->selectRaw('selected_value, count(*) as count')
                ->groupBy('selected_value')
                ->pluck('count', 'selected_value')
                ->toArray();
        } elseif ($dimension->dimension_type === 'numeric_scale') {
            $statistics['average_value'] = $annotationValues->avg('numeric_value');
            $statistics['min_value'] = $annotationValues->min('numeric_value');
            $statistics['max_value'] = $annotationValues->max('numeric_value');
            $statistics['value_distribution'] = $annotationValues
                ->selectRaw('numeric_value, count(*) as count')
                ->groupBy('numeric_value')
                ->pluck('count', 'numeric_value')
                ->toArray();
        }

        return $statistics;
    }
}