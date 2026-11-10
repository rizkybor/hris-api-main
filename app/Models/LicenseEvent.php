<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tracked event of a license (append-only). Written only through
 * App\Services\LicenseEventLogger.
 */
class LicenseEvent extends Model
{
    public const UPDATED_AT = null;

    // Admin (back office / CLI) events.
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const ACTIVATED_BY_ADMIN = 'activated_by_admin';

    public const DEACTIVATED_BY_ADMIN = 'deactivated_by_admin';

    public const EXTENDED = 'extended';

    public const PLAN_CHANGED = 'plan_changed';

    public const DEVICE_RELEASED_BY_ADMIN = 'device_released_by_admin';

    public const DELETED = 'deleted';

    public const CERTIFICATE_DOWNLOADED = 'certificate_downloaded';

    // App events (public API).
    public const DEVICE_ACTIVATED = 'device_activated';

    public const DEVICE_REACTIVATED = 'device_reactivated';

    public const REFRESHED = 'refreshed';

    /** The app on a device reported a different version (meta: from, to). */
    public const APP_UPDATED = 'app_updated';

    public const DEVICE_RELEASED = 'device_released';

    // Denied attempts (activate / refresh on an existing key).
    public const DENIED_DEVICE_LIMIT = 'denied_device_limit';

    public const DENIED_INACTIVE = 'denied_inactive';

    public const DENIED_EXPIRED = 'denied_expired';

    /** A Mac key tried on an iPad, or the other way round. */
    public const DENIED_WRONG_PLATFORM = 'denied_wrong_platform';

    public const EVENTS = [
        self::CREATED,
        self::UPDATED,
        self::ACTIVATED_BY_ADMIN,
        self::DEACTIVATED_BY_ADMIN,
        self::EXTENDED,
        self::PLAN_CHANGED,
        self::DEVICE_RELEASED_BY_ADMIN,
        self::DELETED,
        self::CERTIFICATE_DOWNLOADED,
        self::DEVICE_ACTIVATED,
        self::DEVICE_REACTIVATED,
        self::REFRESHED,
        self::APP_UPDATED,
        self::DEVICE_RELEASED,
        self::DENIED_DEVICE_LIMIT,
        self::DENIED_INACTIVE,
        self::DENIED_EXPIRED,
        self::DENIED_WRONG_PLATFORM,
    ];

    public const LABELS = [
        self::CREATED => 'License created',
        self::UPDATED => 'Details updated',
        self::ACTIVATED_BY_ADMIN => 'Switched on by admin',
        self::DEACTIVATED_BY_ADMIN => 'Switched off by admin',
        self::EXTENDED => 'Expiry changed',
        self::PLAN_CHANGED => 'Plan changed',
        self::DEVICE_RELEASED_BY_ADMIN => 'Device released by admin',
        self::DELETED => 'License deleted',
        self::CERTIFICATE_DOWNLOADED => 'Certificate PDF downloaded',
        self::DEVICE_ACTIVATED => 'Device activated',
        self::DEVICE_REACTIVATED => 'Device re-activated',
        self::REFRESHED => 'License refreshed',
        self::APP_UPDATED => 'App updated',
        self::DEVICE_RELEASED => 'Device released from the app',
        self::DENIED_DEVICE_LIMIT => 'Denied: device limit reached',
        self::DENIED_INACTIVE => 'Denied: license switched off',
        self::DENIED_EXPIRED => 'Denied: license expired',
        self::DENIED_WRONG_PLATFORM => 'Denied: wrong platform',
    ];

    /**
     * A "refreshed" event is logged at most once per device per this many
     * hours; last_seen_at is updated on every refresh.
     */
    public const REFRESH_LOG_INTERVAL_HOURS = 24;

    protected $fillable = [
        'license_id',
        'event',
        'device_id',
        'device_name',
        'platform',
        'app_version',
        'ip',
        'user_id',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? ucfirst(str_replace('_', ' ', $this->event));
    }
}
