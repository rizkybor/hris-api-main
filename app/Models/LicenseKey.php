<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One key of a "one key per device" license: good for exactly one device.
 * available -> in_use (bound to the device that activated it) -> used
 * (that device was released; the key never works again).
 */
class LicenseKey extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_USE = 'in_use';

    public const STATUS_USED = 'used';

    public const STATUSES = [self::STATUS_AVAILABLE, self::STATUS_IN_USE, self::STATUS_USED];

    protected $fillable = [
        'license_id',
        'position',
        'key',
        'status',
        'device_id',
        'device_name',
        'platform',
        'activated_at',
        'used_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'activated_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    public function activation(): HasOne
    {
        return $this->hasOne(LicenseActivation::class, 'license_key_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isInUse(): bool
    {
        return $this->status === self::STATUS_IN_USE;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /** The bound device was released: the key is dead from now on. */
    public function markUsed(): void
    {
        $this->forceFill(['status' => self::STATUS_USED, 'used_at' => now()])->save();
    }
}
