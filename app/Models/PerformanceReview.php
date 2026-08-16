<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class PerformanceReview extends Model
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
        'reviewer_id',
        'period',
        'period_start',
        'period_end',
        'overall_rating',
        'category_scores',
        'strengths',
        'areas_for_improvement',
        'goals_next_period',
        'status',
        'employee_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'overall_rating' => 'decimal:2',
            'category_scores' => 'array',
            'employee_acknowledged_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
