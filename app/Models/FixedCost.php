<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'no', 'financial_items', 'description', 'budget', 'actual'
    ];

    protected $casts = [
        'budget' => 'float',
        'actual' => 'float',
    ];
}