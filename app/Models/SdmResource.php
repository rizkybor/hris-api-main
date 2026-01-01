<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SdmResource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'no', 'sdm_component', 'metrik', 'capacity_target', 'actual', 'rag_status'
    ];

    protected $casts = [
        'capacity_target' => 'float',
        'actual' => 'float',
        'rag_status' => 'string',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sdm_component', 'like', '%'.$search.'%')
                ->orWhere('metrik', 'like', '%'.$search.'%')
                ->orWhere('capacity_target', 'like', '%'.$search.'%')
                ->orWhere('actual', 'like', '%'.$search.'%')
                ->orWhere('rag_status', 'like', '%'.$search.'%');
        });
    }
}