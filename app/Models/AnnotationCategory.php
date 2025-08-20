<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnotationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'dimension_id',
        'category_name',
        'category_value',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    // Relationships
    public function dimension()
    {
        return $this->belongsTo(AnnotationDimension::class, 'dimension_id');
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
