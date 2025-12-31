<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfrastructureTool extends Model
{
    use HasFactory;

    protected $fillable = [
        'no', 'tech_stack_component', 'vendor', 'monthly_fee', 'annual_fee', 'expired_date', 'status'
    ];

    protected $casts = [
        'monthly_fee' => 'float',
        'annual_fee' => 'float',
        'expired_date' => 'date',
    ];
}