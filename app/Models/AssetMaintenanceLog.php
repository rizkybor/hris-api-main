<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMaintenanceLog extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Asset Maintenance');
    }

    protected $fillable = [
        'asset_id',
        'performed_at',
        'description',
        'cost',
        'next_due_date',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'next_due_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(CompanyAsset::class, 'asset_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
