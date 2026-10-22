<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One calendar month of a contract. quota_hours is copied from the
 * contract when the month opens, so changing the contract's default only
 * affects later months and the history stays as it was.
 */
class MaintenancePeriod extends Model
{
    protected $fillable = [
        'maintenance_contract_id',
        'period_month',
        'quota_hours',
        'carried_hours',
        'leftover_action',
        'closed_at',
        'leftover_hours',
        'leftover_applied',
        'leftover_banked_hours',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'quota_hours' => 'float',
            'carried_hours' => 'float',
            'leftover_hours' => 'float',
            'leftover_banked_hours' => 'float',
            'closed_at' => 'datetime',
        ];
    }

    public function contract()
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function adjustments()
    {
        return $this->hasMany(MaintenanceAdjustment::class);
    }

    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Quota + carried over + adjustments, used, remaining and overage. */
    public function totals(): array
    {
        $adjusted = round((float) ($this->adjustments_sum_hours ?? $this->adjustments()->sum('hours')), 2);
        $used = round((float) ($this->logs_sum_hours ?? $this->logs()->sum('hours')), 2);
        $total = round($this->quota_hours + $this->carried_hours + $adjusted, 2);

        return [
            'quota' => $this->quota_hours,
            'carried' => $this->carried_hours,
            'adjusted' => $adjusted,
            'total' => $total,
            'used' => $used,
            'remaining' => round(max(0, $total - $used), 2),
            'overage' => round(max(0, $used - $total), 2),
            'used_percent' => $total > 0 ? round($used / $total * 100, 1) : ($used > 0 ? 100.0 : 0.0),
        ];
    }
}
