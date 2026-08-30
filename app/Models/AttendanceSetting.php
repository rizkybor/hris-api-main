<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'allow_weekend_check_in',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_weekend_check_in' => 'boolean',
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
