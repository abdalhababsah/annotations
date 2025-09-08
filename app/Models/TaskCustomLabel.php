<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskCustomLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'name',
        'color',
        'description',
        'created_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taskSegments(): HasMany
    {
        return $this->hasMany(TaskSegment::class, 'custom_label_id');
    }
}