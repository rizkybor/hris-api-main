<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfrastructureTool extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'no', 'tech_stack_component', 'vendor', 'monthly_fee', 'annual_fee', 'expired_date', 'status'
    ];

    protected $casts = [
        'monthly_fee' => 'float',
        'annual_fee' => 'float',
        'expired_date' => 'date',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('tech_stack_component', 'like', '%'.$search.'%')
                ->orWhere('vendor', 'like', '%'.$search.'%')
                ->orWhere('monthly_fee', 'like', '%'.$search.'%')
                ->orWhere('annual_fee', 'like', '%'.$search.'%')
                ->orWhere('expired_date', 'like', '%'.$search.'%')
                ->orWhere('status', 'like', '%'.$search.'%');
        });
    }
}