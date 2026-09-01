<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class CompanyAsset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Asset');
    }

    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'warranty_expiry_date',
        'useful_life_months',
        'depreciation_method',
        'next_maintenance_due_date',
        'supplier_vendor_id',
        'condition',
        'status',
        'assigned_to',
        'assigned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_expiry_date' => 'date',
            'next_maintenance_due_date' => 'date',
            'assigned_at' => 'date',
        ];
    }

    public function assignee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'assigned_to');
    }

    public function assignmentHistories()
    {
        return $this->hasMany(AssetAssignmentHistory::class, 'asset_id');
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(AssetMaintenanceLog::class, 'asset_id')->orderByDesc('performed_at');
    }

    public function supplier()
    {
        return $this->belongsTo(Vendors::class, 'supplier_vendor_id');
    }

    public function getIsUnderWarrantyAttribute(): ?bool
    {
        if (! $this->warranty_expiry_date) {
            return null;
        }

        return $this->warranty_expiry_date->isFuture();
    }

    /**
     * Straight-line depreciation to a Rp 0 residual value -- the only
     * method actually computed (see depreciation_method's own column
     * comment). Null when purchase_price/purchase_date/useful_life_months
     * aren't all set, since there isn't enough information to compute a
     * book value at all.
     */
    public function getCurrentBookValueAttribute(): ?float
    {
        if (! $this->purchase_price || ! $this->purchase_date || ! $this->useful_life_months) {
            return null;
        }

        $monthsElapsed = min($this->useful_life_months, max(0, (int) $this->purchase_date->diffInMonths(now())));
        $monthlyDepreciation = (float) $this->purchase_price / $this->useful_life_months;
        $accumulatedDepreciation = $monthlyDepreciation * $monthsElapsed;

        return round(max(0, (float) $this->purchase_price - $accumulatedDepreciation), 2);
    }
}
