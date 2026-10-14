<?php

namespace App\Models;

use App\Enums\OperationalCostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class InfrastructureTool extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Finance');
    }

    protected $fillable = [
        'tech_stack_component', 'vendor', 'monthly_fee', 'annual_fee', 'expired_date', 'status', 'notes'
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'annual_fee' => 'decimal:2',
        'expired_date' => 'date',
    ];

    /** Items JC Digital actually uses -- the only ones counted in totals. */
    public function scopeInUse($query)
    {
        return $query->where('status', OperationalCostStatus::ACTIVE->value);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('tech_stack_component', 'like', '%'.$search.'%')
                ->orWhere('vendor', 'like', '%'.$search.'%')
                ->orWhere('expired_date', 'like', '%'.$search.'%')
                ->orWhere('status', 'like', '%'.$search.'%');
        });
    }
}