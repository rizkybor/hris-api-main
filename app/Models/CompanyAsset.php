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
}
