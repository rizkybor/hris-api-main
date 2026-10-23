<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Holiday extends Model
{
    use LogsActivity;

    protected $fillable = ['date', 'name', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('Holiday');
    }

    /** Holiday names keyed by "Y-m-d" within the range. */
    public static function between(string $start, string $end): array
    {
        return static::whereBetween('date', [$start, $end])->get()
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();
    }
}
