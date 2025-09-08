<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewSegmentChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'segment_id',
        'change_type',
        'original_start_time',
        'original_end_time',
        'original_project_label_id',
        'original_custom_label_id',
        'new_start_time',
        'new_end_time',
        'new_project_label_id',
        'new_custom_label_id',
        'change_reason',
    ];

    protected $casts = [
        'original_start_time' => 'decimal:3',
        'original_end_time' => 'decimal:3',
        'new_start_time' => 'decimal:3',
        'new_end_time' => 'decimal:3',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(TaskSegment::class, 'segment_id');
    }

    public function originalProjectLabel(): BelongsTo
    {
        return $this->belongsTo(SegmentationLabel::class, 'original_project_label_id');
    }

    public function originalCustomLabel(): BelongsTo
    {
        return $this->belongsTo(TaskCustomLabel::class, 'original_custom_label_id');
    }

    public function newProjectLabel(): BelongsTo
    {
        return $this->belongsTo(SegmentationLabel::class, 'new_project_label_id');
    }

    public function newCustomLabel(): BelongsTo
    {
        return $this->belongsTo(TaskCustomLabel::class, 'new_custom_label_id');
    }
}