<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_size',
        'mime_type',
        'media_type',
        'duration',
        'dimensions',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('media_type', $type);
    }

    public function scopeAudio($query)
    {
        return $query->where('media_type', 'audio');
    }

    public function scopeImage($query)
    {
        return $query->where('media_type', 'image');
    }

    // Helper methods
    public function isAudio()
    {
        return $this->media_type === 'audio';
    }

    public function isImage()
    {
        return $this->media_type === 'image';
    }

    public function getFileSizeHumanAttribute()
    {
        if (!$this->file_size) return 'Unknown';
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationFormattedAttribute()
    {
        if (!$this->duration) return null;
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}