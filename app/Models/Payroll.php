<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'salary_month',
        'payment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
