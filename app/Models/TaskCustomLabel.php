<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class TaskCustomLabel extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'name', 'color', 'description', 'created_by', 'uuid'];



    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the task that owns this custom label
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who created this custom label
     */
    protected static function booted()
    {
        static::creating(function ($label) {
            if (empty($label->uuid)) {
                $label->uuid = (string) Str::uuid();
            }
        });
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all segments using this custom label
     */
    public function segments()
    {
        return $this->hasMany(TaskSegment::class, 'custom_label_id');
    }

    /**
     * Scope to get labels for a specific task
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope to get labels created by a specific user
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }
}

