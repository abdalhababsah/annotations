<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'label_name',
        'label_value',
        'description',
        'suggested_values',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'suggested_values' => 'array',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // Helper methods
    public function getSuggestedValuesArray()
    {
        return $this->suggested_values ?? [];
    }
}