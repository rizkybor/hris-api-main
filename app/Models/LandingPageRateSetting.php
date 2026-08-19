<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LandingPageRateSetting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project Calculator');
    }

    protected $fillable = [
        'server_dedicated_price',
        'server_shared_price',
        'design_dedicated_price',
        'design_template_price',
    ];

    protected $casts = [
        'server_dedicated_price' => 'decimal:2',
        'server_shared_price' => 'decimal:2',
        'design_dedicated_price' => 'decimal:2',
        'design_template_price' => 'decimal:2',
    ];
}
