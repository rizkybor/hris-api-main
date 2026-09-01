<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'allow_weekend_check_in',
        'office_latitude',
        'office_longitude',
        'office_radius_meters',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_weekend_check_in' => 'boolean',
            'office_latitude' => 'float',
            'office_longitude' => 'float',
            'office_radius_meters' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create(['allow_weekend_check_in' => false]);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
