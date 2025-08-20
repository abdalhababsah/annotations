<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'metric_type',
        'metric_value',
        'calculation_period',
        'calculated_at',
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeByPeriod($query, $period)
    {
        return $query->where('calculation_period', $period);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('calculated_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getFormattedValueAttribute()
    {
        switch ($this->metric_type) {
            case 'accuracy_rate':
            case 'consistency_rate':
            case 'completion_rate':
            case 'review_pass_rate':
                return round((float)$this->metric_value * 100, 1) . '%';
            
            case 'average_quality_score':
                return round((float)$this->metric_value, 2) . '/1.0';
            
            case 'productivity_score':
                return round((float)$this->metric_value, 2) . ' tasks/hour';
            
            case 'inter_annotator_agreement':
                return round((float)$this->metric_value, 3);
            
            default:
                return $this->metric_value;
        }
    }

    public function getMetricDescription()
    {
        $descriptions = [
            'accuracy_rate' => 'Percentage of annotations that passed quality review',
            'consistency_rate' => 'Consistency of annotations across similar tasks',
            'inter_annotator_agreement' => 'Agreement level between different annotators',
            'completion_rate' => 'Percentage of assigned tasks completed on time',
            'average_quality_score' => 'Average quality score from reviews',
            'productivity_score' => 'Number of tasks completed per hour',
            'review_pass_rate' => 'Percentage of annotations approved on first review',
        ];

        return $descriptions[$this->metric_type] ?? 'Quality metric';
    }

    public function isGoodPerformance()
    {
        switch ($this->metric_type) {
            case 'accuracy_rate':
            case 'consistency_rate':
            case 'completion_rate':
            case 'review_pass_rate':
                return $this->metric_value >= 0.8; // 80% or higher
            
            case 'average_quality_score':
                return $this->metric_value >= 0.75; // 75% or higher
            
            case 'inter_annotator_agreement':
                return $this->metric_value >= 0.7; // 70% or higher
            
            case 'productivity_score':
                return $this->metric_value >= 1.0; // 1 task per hour or more
            
            default:
                return false;
        }
    }
}