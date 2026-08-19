<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProjectCalculation extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project Calculator');
    }

    protected $fillable = [
        'name',
        'client_name',
        'scenario',
        'rate_cost_per_hour',
        'rate_sell_per_hour',
        'pm_overhead_percent',
        'infra_setup_cost',
        'rate_setting_snapshot',
        'items',
        'subtotal',
        'buffer_total',
        'pm_overhead_total',
        'margin_percent',
        'margin_total',
        'grand_total',
        'include_ppn',
        'ppn_percent',
        'total_with_ppn',
        'include_pph',
        'pph_type',
        'pph_percent',
        'pph_amount',
        'net_received',
        'estimated_duration_weeks',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rate_cost_per_hour' => 'decimal:2',
        'rate_sell_per_hour' => 'decimal:2',
        'pm_overhead_percent' => 'decimal:2',
        'infra_setup_cost' => 'decimal:2',
        'rate_setting_snapshot' => 'array',
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'buffer_total' => 'decimal:2',
        'pm_overhead_total' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'margin_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'include_ppn' => 'boolean',
        'ppn_percent' => 'decimal:2',
        'total_with_ppn' => 'decimal:2',
        'include_pph' => 'boolean',
        'pph_percent' => 'decimal:2',
        'pph_amount' => 'decimal:2',
        'net_received' => 'decimal:2',
        'estimated_duration_weeks' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
