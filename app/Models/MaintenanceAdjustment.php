<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A signed, reasoned change to one month's quota (+5 jam, -2 jam). */
class MaintenanceAdjustment extends Model
{
    use LogsActivity;

    protected $fillable = ['maintenance_period_id', 'hours', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['hours' => 'float'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->useLogName('Maintenance');
    }

    public function period()
    {
        return $this->belongsTo(MaintenancePeriod::class, 'maintenance_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
