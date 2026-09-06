<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    protected $fillable = [
        'attendance_exempt_roles_enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_exempt_roles_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create(['attendance_exempt_roles_enabled' => false]);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
