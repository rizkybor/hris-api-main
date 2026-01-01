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
        'saldo_company' => 'float'
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('saldo_company', 'like', '%'.$search.'%');
        });
    }
}