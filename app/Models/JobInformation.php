<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class JobInformation extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Employee');
    }

    protected $fillable = [
        'employee_id',
        'job_title',
        'team_id',
        'years_experience',
        'status',
        'employment_type',
        'work_location',
        'start_date',
        'monthly_salary',
        'skill_level',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
