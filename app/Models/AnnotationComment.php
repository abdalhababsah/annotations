<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnotationComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'user_id',
        'comment_text',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    // Relationships
    public function annotation()
    {
        return $this->belongsTo(Annotation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    // Helper methods
    public function isInternal()
    {
        return $this->is_internal;
    }

    public function isPublic()
    {
        return !$this->is_internal;
    }
}