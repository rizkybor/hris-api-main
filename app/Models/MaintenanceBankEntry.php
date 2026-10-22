<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One movement in a contract's development balance. Credits (leftover,
 * adjust_in, refund) carry remaining_hours/expires_at; debits (feature,
 * adjust_out, expire) are negative.
 */
class MaintenanceBankEntry extends Model
{
    public const CREDIT_TYPES = ['leftover', 'adjust_in', 'refund'];

    protected $fillable = [
        'maintenance_contract_id',
        'type',
        'hours',
        'remaining_hours',
        'expires_at',
        'maintenance_period_id',
        'maintenance_feature_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'float',
            'remaining_hours' => 'float',
            'expires_at' => 'date',
        ];
    }

    public function contract()
    {
        return $this->belongsTo(MaintenanceContract::class, 'maintenance_contract_id');
    }

    public function period()
    {
        return $this->belongsTo(MaintenancePeriod::class, 'maintenance_period_id');
    }

    public function feature()
    {
        return $this->belongsTo(MaintenanceFeature::class, 'maintenance_feature_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
