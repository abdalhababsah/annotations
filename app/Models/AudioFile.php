<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AudioFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_size',
        'mime_type',
        'duration',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration' => 'decimal:2',
    ];

    protected $appends = ['url'];

    // Relationships
    public function project() { return $this->belongsTo(Project::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function tasks() { return $this->hasMany(Task::class); }

    // Accessors
    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->file_path);
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $like = "%{$term}%";
        return $q->where(function ($qq) use ($like) {
            $qq->where('original_filename', 'like', $like)
               ->orWhere('stored_filename', 'like', $like)
               ->orWhere('mime_type', 'like', $like);
        });
    }

    public function scopeForProject($q, int $projectId) { return $q->where('project_id', $projectId); }
}
