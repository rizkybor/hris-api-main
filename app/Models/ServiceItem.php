<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A service from the Rate Card, offered through Client Screening. */
class ServiceItem extends Model
{
    use LogsActivity;

    public const PRICING_MODES = ['rate_card', 'hourly', 'by_quote'];

    public const PRICE_UNITS = ['once', 'month', 'year'];

    protected $fillable = [
        'code', 'name', 'category', 'description', 'pricing_mode', 'price_from', 'price_unit',
        'default_hours', 'duration_text', 'availability', 'is_active', 'sort_order', 'rate_card_ref', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price_from' => 'decimal:2',
            'default_hours' => 'decimal:2',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('Service Catalog');
    }
}
