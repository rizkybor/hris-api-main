<?php

namespace App\Models;

use App\Enums\OperationalCostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class SdmResource extends Model
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
        'sdm_component', 'sdm_field_id', 'productive_hours_per_month', 'capacity_target', 'actual', 'status', 'expired_date', 'notes'
    ];

    protected $casts = [
        'actual' => 'decimal:2',
        'productive_hours_per_month' => 'decimal:2',
        'expired_date' => 'date',
    ];

    /** Items JC Digital actually uses -- the only ones counted in totals. */
    public function scopeInUse($query)
    {
        return $query->where('status', OperationalCostStatus::ACTIVE->value);
    }

    public function field()
    {
        return $this->belongsTo(SdmField::class, 'sdm_field_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sdm_component', 'like', '%'.$search.'%')
                ->orWhere('capacity_target', 'like', '%'.$search.'%')
                ->orWhereHas('field', fn ($fq) => $fq->where('name', 'like', '%'.$search.'%'));
        });
    }
}