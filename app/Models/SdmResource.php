<?php

namespace App\Models;

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
        'sdm_component', 'sdm_field_id', 'productive_hours_per_month', 'metrik', 'capacity_target', 'budget', 'actual', 'rag_status', 'notes'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'actual' => 'decimal:2',
        'productive_hours_per_month' => 'decimal:2',
    ];

    public function field()
    {
        return $this->belongsTo(SdmField::class, 'sdm_field_id');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sdm_component', 'like', '%'.$search.'%')
                ->orWhere('metrik', 'like', '%'.$search.'%')
                ->orWhere('capacity_target', 'like', '%'.$search.'%')
                ->orWhere('rag_status', 'like', '%'.$search.'%')
                ->orWhereHas('field', fn ($fq) => $fq->where('name', 'like', '%'.$search.'%'));
        });
    }
}