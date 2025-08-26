<?php
// AudioFile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // Helper methods
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) return '00:00';
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
