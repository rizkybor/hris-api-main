<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ScreeningTemplate extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'definition', 'is_default', 'updated_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->latest('id')->first() ?? static::latest('id')->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'is_default'])->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('Screening Template');
    }
}
