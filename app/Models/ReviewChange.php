<?php
// ReviewChange.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'dimension_id',
        'original_value',
        'corrected_value',
        'original_numeric',
        'corrected_numeric',
        'change_reason',
    ];

    // Relationships
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function dimension()
    {
        return $this->belongsTo(AnnotationDimension::class);
    }

    // Helper methods
    public function getOriginalDisplayValueAttribute()
    {
        if ($this->dimension->isCategorical()) {
            return $this->original_value;
        } elseif ($this->dimension->isNumericScale()) {
            return $this->original_numeric;
        }
        
        return null;
    }

    public function getCorrectedDisplayValueAttribute()
    {
        if ($this->dimension->isCategorical()) {
            return $this->corrected_value;
        } elseif ($this->dimension->isNumericScale()) {
            return $this->corrected_numeric;
        }
        
        return null;
    }

    public function hasChange()
    {
        if ($this->dimension->isCategorical()) {
            return $this->original_value !== $this->corrected_value;
        } elseif ($this->dimension->isNumericScale()) {
            return $this->original_numeric !== $this->corrected_numeric;
        }
        
        return false;
    }
}
