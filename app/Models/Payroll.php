<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Payroll extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Payroll');
    }

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
