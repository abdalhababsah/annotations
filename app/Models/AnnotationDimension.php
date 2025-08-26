<?php
// AnnotationDimension.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnotationDimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'dimension_type',
        'scale_min',
        'scale_max',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function dimensionValues()
    {
        return $this->hasMany(DimensionValue::class, 'dimension_id')->orderBy('display_order');
    }

    public function annotationValues()
    {
        return $this->hasMany(AnnotationValue::class, 'dimension_id');
    }

    // Scopes
    public function scopeCategorical($query)
    {
        return $query->where('dimension_type', 'categorical');
    }

    public function scopeNumericScale($query)
    {
        return $query->where('dimension_type', 'numeric_scale');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    // Helper methods
    public function isCategorical()
    {
        return $this->dimension_type === 'categorical';
    }

    public function isNumericScale()
    {
        return $this->dimension_type === 'numeric_scale';
    }

    public function getScaleRangeAttribute()
    {
        if ($this->isNumericScale()) {
            return range($this->scale_min, $this->scale_max);
        }
        return [];
    }
}