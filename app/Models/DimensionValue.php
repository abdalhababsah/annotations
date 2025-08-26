<?php
// DimensionValue.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DimensionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'dimension_id',
        'value',
        'label',
        'display_order',
    ];

    // Relationships
    public function dimension()
    {
        return $this->belongsTo(AnnotationDimension::class, 'dimension_id');
    }

    // Scopes
    public function scopeOrderedByDisplay($query)
    {
        return $query->orderBy('display_order');
    }

    // Helper methods
    public function getDisplayLabelAttribute()
    {
        return $this->label ?: $this->value;
    }
}
