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
        'sdm_component', 'metrik', 'capacity_target', 'budget', 'actual', 'rag_status', 'notes'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'actual' => 'decimal:2',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sdm_component', 'like', '%'.$search.'%')
                ->orWhere('metrik', 'like', '%'.$search.'%')
                ->orWhere('capacity_target', 'like', '%'.$search.'%')
                ->orWhere('rag_status', 'like', '%'.$search.'%');
        });
    }
}