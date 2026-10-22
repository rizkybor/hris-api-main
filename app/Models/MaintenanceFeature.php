<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A new feature paid for from the contract's development balance. Approving
 * it reserves its estimate from the balance; finishing (or cancelling) it
 * settles against the hours actually logged.
 */
class MaintenanceFeature extends Model
{
    use LogsActivity;

    public const STATUSES = ['proposed', 'approved', 'in_progress', 'done', 'cancelled'];

    protected $fillable = [
        'maintenance_contract_id',
        'title',
        'description',
        'estimated_hours',
        'status',
        'reserved_hours',
        'reserved_expires_at',
        'billable_hours',
        'visible_to_client',
        'approved_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_hours' => 'float',
            'reserved_hours' => 'float',
            'billable_hours' => 'float',
            'reserved_expires_at' => 'date',
            'visible_to_client' => 'boolean',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('Maintenance');
    }

    public function contract()
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['approved', 'in_progress'], true);
    }

    public function isSettled(): bool
    {
        return in_array($this->status, ['done', 'cancelled'], true);
    }

    public function usedHours(): float
    {
        return round((float) ($this->logs_sum_hours ?? $this->logs()->sum('hours')), 2);
    }
}
