<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'annotation_id',
        'project_label_id',
        'custom_label_id',
        'start_time',
        'end_time',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'decimal:3',
        'end_time' => 'decimal:3',
        'duration' => 'decimal:3',
    ];

    protected $appends = [
        'label_name',
        'label_color',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    public function projectLabel(): BelongsTo
    {
        return $this->belongsTo(SegmentationLabel::class, 'project_label_id');
    }

    public function customLabel(): BelongsTo
    {
        return $this->belongsTo(TaskCustomLabel::class, 'custom_label_id');
    }

    public function reviewChanges(): HasMany
    {
        return $this->hasMany(ReviewSegmentChange::class, 'segment_id');
    }

    // Accessor to get the label name regardless of type
    public function getLabelNameAttribute(): string
    {
        return $this->projectLabel?->name ?? $this->customLabel?->name ?? 'Unknown';
    }

    // Accessor to get the label color regardless of type
    public function getLabelColorAttribute(): string
    {
        return $this->projectLabel?->color ?? $this->customLabel?->color ?? '#6B7280';
    }

    // Scope to get segments with their labels
    public function scopeWithLabels($query)
    {
        return $query->with(['projectLabel', 'customLabel']);
    }

    // Check if segment uses project label
    public function isProjectLabel(): bool
    {
        return !is_null($this->project_label_id);
    }

    // Check if segment uses custom label
    public function isCustomLabel(): bool
    {
        return !is_null($this->custom_label_id);
    }
}
