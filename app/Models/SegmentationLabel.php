<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SegmentationLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_segmentation_labels', 'label_id', 'project_id')
                    ->withPivot('display_order')
                    ->withTimestamps()
                    ->orderBy('project_segmentation_labels.display_order');
    }

    public function taskSegments(): HasMany
    {
        return $this->hasMany(TaskSegment::class, 'project_label_id');
    }
}
