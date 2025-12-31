<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SdmResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'no', 'sdm_component', 'metrik', 'capacity_target', 'actual', 'rag_status'
    ];

    protected $casts = [
        'capacity_target' => 'float',
        'actual' => 'float',
        'rag_status' => 'string',
    ];
}