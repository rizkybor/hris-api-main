<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A client's maintenance agreement: a monthly hour quota, what happens to
 * the hours left over at month end, and the development balance those
 * hours can be moved into. See MaintenanceService for the monthly cycle.
 */
class MaintenanceContract extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = ['active', 'paused', 'ended'];

    public const LEFTOVER_POLICIES = ['expire', 'rollover', 'bank'];

    protected $fillable = [
        'client_id',
        'project_id',
        'name',
        'monthly_quota_hours',
        'start_date',
        'end_date',
        'status',
        'leftover_policy',
        'bank_cap_hours',
        'bank_expiry_months',
        'overage_rate',
        'notes',
        'created_by',
    ];

    protected $hidden = ['share_token'];

    protected function casts(): array
    {
        return [
            'monthly_quota_hours' => 'float',
            'bank_cap_hours' => 'float',
            'bank_expiry_months' => 'integer',
            'overage_rate' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
            'share_last_viewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Maintenance');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periods()
    {
        return $this->hasMany(MaintenancePeriod::class);
    }

    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function features()
    {
        return $this->hasMany(MaintenanceFeature::class);
    }

    public function bankEntries()
    {
        return $this->hasMany(MaintenanceBankEntry::class);
    }

    public function shareUrl(): ?string
    {
        return $this->share_token ? route('maintenance.public', $this->share_token) : null;
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%{$search}%"));
        });
    }
}
