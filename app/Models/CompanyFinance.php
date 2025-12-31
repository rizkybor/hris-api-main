<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'saldo_company'
    ];

    protected $casts = [
        'saldo_company' => 'float'
    ];
}