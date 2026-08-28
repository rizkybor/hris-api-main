<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompanyCashBalance extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Company Cash Book');
    }

    protected $fillable = [
        'opening_balance',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
        ];
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The single row this whole table ever holds -- created with a 0
     * opening balance on first access if it doesn't exist yet. Always the
     * row with the lowest id, so even if something ever inserts a second
     * row by mistake, reads/writes stay pinned to the original one instead
     * of silently drifting to whichever row happens to sort last.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create(['opening_balance' => 0]);
    }
}
