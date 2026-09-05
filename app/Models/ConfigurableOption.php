<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigurableOption extends Model
{
    protected $fillable = [
        'category',
        'value',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const CATEGORIES = [
        'employment_type',
        'ptkp_status',
        'bank_name',
        'preferred_language',
        'subscription_service_type',
    ];

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
