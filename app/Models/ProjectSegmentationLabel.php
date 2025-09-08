<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSegmentationLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'label_id',
        'display_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(SegmentationLabel::class, 'label_id');
    }
}