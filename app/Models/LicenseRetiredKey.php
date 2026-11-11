<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A key that a plan change replaced: it never works again.
 */
class LicenseRetiredKey extends Model
{
    public const REASON_PLAN_CHANGED = 'plan_changed';

    protected $fillable = ['license_id', 'key', 'plan', 'reason', 'retired_at'];

    protected $casts = ['retired_at' => 'datetime'];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
