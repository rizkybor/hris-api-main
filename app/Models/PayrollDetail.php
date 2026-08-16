<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class PayrollDetail extends Model
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
        'payroll_id',
        'employee_id',
        'original_salary',
        'gross_salary',
        'final_salary',
        'attended_days',
        'sick_days',
        'absent_days',
        'bpjs_kesehatan_employee',
        'bpjs_jht_employee',
        'bpjs_jp_employee',
        'bpjs_kesehatan_company',
        'bpjs_jht_company',
        'bpjs_jp_company',
        'bpjs_jkk_company',
        'bpjs_jkm_company',
        'pph21',
        'total_deduction',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_salary' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'final_salary' => 'decimal:2',
            'bpjs_kesehatan_employee' => 'decimal:2',
            'bpjs_jht_employee' => 'decimal:2',
            'bpjs_jp_employee' => 'decimal:2',
            'bpjs_kesehatan_company' => 'decimal:2',
            'bpjs_jht_company' => 'decimal:2',
            'bpjs_jp_company' => 'decimal:2',
            'bpjs_jkk_company' => 'decimal:2',
            'bpjs_jkm_company' => 'decimal:2',
            'pph21' => 'decimal:2',
            'total_deduction' => 'decimal:2',
        ];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
