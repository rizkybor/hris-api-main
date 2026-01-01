<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyFinance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'saldo_company'
    ];

    protected $casts = [
        'saldo_company' => 'decimal:2'
    ];
}