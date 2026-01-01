<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SdmResource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sdm_component', 'metrik', 'capacity_target', 'budget', 'actual', 'rag_status', 'notes'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'actual' => 'decimal:2',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sdm_component', 'like', '%'.$search.'%')
                ->orWhere('metrik', 'like', '%'.$search.'%')
                ->orWhere('capacity_target', 'like', '%'.$search.'%')
                ->orWhere('rag_status', 'like', '%'.$search.'%');
        });
    }
}