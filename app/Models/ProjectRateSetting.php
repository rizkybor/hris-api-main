<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectRateSetting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project Calculator');
    }

    protected $fillable = [
        'team_monthly_cost',
        'productive_hours_per_person',
        'team_size',
        'margin_multiplier',
        'pm_overhead_percent',
        'default_infra_setup_cost',
    ];

    protected $casts = [
        'team_monthly_cost' => 'decimal:2',
        'productive_hours_per_person' => 'decimal:2',
        'team_size' => 'integer',
        'margin_multiplier' => 'decimal:2',
        'pm_overhead_percent' => 'decimal:2',
        'default_infra_setup_cost' => 'decimal:2',
    ];

    public function getRateCostPerHourAttribute(): float
    {
        $totalProductiveHours = $this->productive_hours_per_person * $this->team_size;

        return $totalProductiveHours > 0
            ? round(((float) $this->team_monthly_cost) / $totalProductiveHours, 2)
            : 0;
    }

    public function getRateSellPerHourAttribute(): float
    {
        return round($this->rate_cost_per_hour * (float) $this->margin_multiplier, 2);
    }
}
