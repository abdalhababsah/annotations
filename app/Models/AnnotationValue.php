<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnotationValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'annotation_id',
        'dimension_id',
        'value_numeric',
        'value_text',
        'value_boolean',
        'value_categorical',
        'value_form_data',
        'confidence_score',
        'notes',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'value_boolean' => 'boolean',
        'value_form_data' => 'array',
        'confidence_score' => 'decimal:2',
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
        if ($this->value_numeric !== null) return $this->value_numeric;
        if ($this->value_text !== null) return $this->value_text;
        if ($this->value_boolean !== null) return $this->value_boolean;
        if ($this->value_categorical !== null) return $this->value_categorical;
        if ($this->value_form_data !== null) return $this->value_form_data;
        
        return null;
    }

    public function getFormattedValue()
    {
        $dimension = $this->dimension;
        
        if ($dimension->isNumericScale() && $this->value_numeric !== null) {
            $labels = $dimension->scale_labels;
            $value = (string) $this->value_numeric;
            
            if ($labels && isset($labels[$value])) {
                return "{$this->value_numeric} - {$labels[$value]}";
            }
            
            return $this->value_numeric;
        }
        
        if ($dimension->isBoolean() && $this->value_boolean !== null) {
            return $this->value_boolean ? 'Yes' : 'No';
        }
        
        if ($dimension->isRepeatableForm() && $this->value_form_data !== null) {
            $data = $this->value_form_data;
            
            if (isset($data['objects'])) {
                return count($data['objects']) . ' objects detected';
            }
            
            if (isset($data['segments'])) {
                return count($data['segments']) . ' segments marked';
            }
            
            return 'Form data available';
        }
        
        return $this->getValue();
    }

    public function getObjectsCount()
    {
        if (!$this->value_form_data || !isset($this->value_form_data['objects'])) {
            return 0;
        }
        
        return count($this->value_form_data['objects']);
    }

    public function getSegmentsCount()
    {
        if (!$this->value_form_data || !isset($this->value_form_data['segments'])) {
            return 0;
        }
        
        return count($this->value_form_data['segments']);
    }
}
