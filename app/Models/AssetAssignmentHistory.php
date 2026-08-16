<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class AssetAssignmentHistory extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Asset');
    }

    protected $fillable = [
        'asset_id',
        'employee_id',
        'assigned_by',
        'assigned_at',
        'returned_at',
        'condition_at_assignment',
        'condition_at_return',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'returned_at' => 'date',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(CompanyAsset::class, 'asset_id');
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
