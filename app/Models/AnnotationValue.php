<?php
// AnnotationValue.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnotationValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'dimension_id',
        'selected_value',
        'numeric_value',
        'notes',
    ];

    // Relationships
    public function annotation()
    {
        return $this->belongsTo(Annotation::class);
    }

    public function dimension()
    {
        return $this->belongsTo(AnnotationDimension::class);
    }

    // Helper methods
    public function getValue()
    {
        if ($this->dimension->isCategorical()) {
            return $this->selected_value;
        } elseif ($this->dimension->isNumericScale()) {
            return $this->numeric_value;
        }
        
        return null;
    }

    public function getDisplayValueAttribute()
    {
        $value = $this->getValue();
        
        if ($this->dimension->isCategorical()) {
            $dimensionValue = $this->dimension->dimensionValues()->where('value', $value)->first();
            return $dimensionValue ? $dimensionValue->display_label : $value;
        }
        
        return $value;
    }
}
