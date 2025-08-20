<?php

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
        'scale_labels',
        'form_template',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'scale_min' => 'integer',
        'scale_max' => 'integer',
        'scale_labels' => 'array',
        'form_template' => 'array',
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function annotationCategories()
    {
        return $this->hasMany(AnnotationCategory::class, 'dimension_id')->orderBy('display_order');
    }

    public function annotationValues()
    {
        return $this->hasMany(AnnotationValue::class, 'dimension_id');
    }

    // Scopes
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('dimension_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // Helper methods
    public function isNumericScale()
    {
        return $this->dimension_type === 'numeric_scale';
    }

    public function isCategorical()
    {
        return $this->dimension_type === 'categorical';
    }

    public function isBoolean()
    {
        return $this->dimension_type === 'boolean';
    }

    public function isText()
    {
        return $this->dimension_type === 'text';
    }

    public function isRepeatableForm()
    {
        return $this->dimension_type === 'repeatable_form';
    }

    public function getScaleRange()
    {
        if (!$this->isNumericScale()) return null;
        return range($this->scale_min, $this->scale_max);
    }
}
