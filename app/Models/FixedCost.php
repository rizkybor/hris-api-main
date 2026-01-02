<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedCost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'financial_items', 'description', 'budget', 'actual', 'notes'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'actual' => 'decimal:2'
    ];

        public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('financial_items', 'like', '%'.$search.'%');
        });
    }
}