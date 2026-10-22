<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Hours worked for a client: routine maintenance (draws on the month's
 * quota) or work on a new feature (draws on the development balance).
 */
class MaintenanceLog extends Model
{
    use LogsActivity;

    public const CATEGORIES = ['bug_fix', 'content_update', 'minor_feature', 'server', 'consultation', 'other'];

    protected $fillable = [
        'maintenance_contract_id',
        'maintenance_period_id',
        'maintenance_feature_id',
        'work_date',
        'title',
        'description',
        'hours',
        'category',
        'performed_by',
        'visible_to_client',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'float',
            'visible_to_client' => 'boolean',
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

    public function period()
    {
        return $this->belongsTo(MaintenancePeriod::class, 'maintenance_period_id');
    }

    public function feature()
    {
        return $this->belongsTo(MaintenanceFeature::class, 'maintenance_feature_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
