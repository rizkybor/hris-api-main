<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A single-use over-the-air install link for the iPad installer (.ipa) of
 * a version. One link installs the app once: it becomes "used" when the
 * .ipa has been streamed completely. "expired" is not stored; it is
 * computed from expires_at (status()).
 *
 * The token is never stored in plain text: token_hash (SHA-256) finds the
 * link, token_encrypted (APP_KEY) lets the back office show an active
 * link again.
 */
class LicenseInstallLink extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_USED => 'Used',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public const TOKEN_LENGTH = 40;

    /**
     * Only one download of a link runs at a time. The lease is renewed
     * while the file streams and released when it stops; a download that
     * died without the server noticing frees the link after this long.
     */
    public const DOWNLOAD_LEASE_SECONDS = 60;

    protected $fillable = [
        'release_id',
        'license_id',
        'device_name',
        'note',
        'token_hash',
        'token_encrypted',
        'created_by',
        'expires_at',
        'status',
        'used_at',
        'used_ip',
        'used_user_agent',
        'revoked_at',
        'revoked_by',
        'page_opened_at',
        'manifest_fetched_at',
        'download_attempts',
        'download_lease_id',
        'download_lease_until',
    ];

    protected $hidden = ['token_hash', 'token_encrypted', 'download_lease_id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'page_opened_at' => 'datetime',
        'manifest_fetched_at' => 'datetime',
        'download_lease_until' => 'datetime',
        'download_attempts' => 'integer',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(LicenseRelease::class, 'release_id')->withTrashed();
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * A new random token: [plain token, attributes to store].
     *
     * @return array{0: string, 1: array{token_hash: string, token_encrypted: string}}
     */
    public static function newToken(): array
    {
        $token = Str::random(self::TOKEN_LENGTH);

        return [$token, ['token_hash' => self::hash($token), 'token_encrypted' => Crypt::encryptString($token)]];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hash($token))->first();
    }

    /**
     * The plain token (null when APP_KEY changed since it was made).
     */
    public function token(): ?string
    {
        try {
            return Crypt::decryptString($this->token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * active / used / revoked as stored; an active link past expires_at
     * is "expired".
     */
    public function status(): string
    {
        if ($this->status === self::STATUS_ACTIVE && $this->expires_at !== null && $this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    public function isUsable(): bool
    {
        return $this->status() === self::STATUS_ACTIVE;
    }

    /**
     * Links that can still be used (stored active and not past expiry).
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('expires_at', '>', now());
    }

    /**
     * Absolute URL under APP_URL (iOS needs https):
     * /api/licenses/{product}/install/{token}[/manifest.plist|/app.ipa].
     */
    public static function url(string $productCode, string $token, string $suffix = ''): string
    {
        return rtrim((string) config('app.url'), '/')."/api/licenses/{$productCode}/install/{$token}".$suffix;
    }

    public static function appUrlIsHttps(): bool
    {
        return str_starts_with(strtolower((string) config('app.url')), 'https://');
    }

    /**
     * Takes the download lease when the link is usable and no other
     * download of it is running. Atomic (one UPDATE), so two parallel
     * downloads cannot both start. Returns the lease id, or null.
     */
    public function claimDownload(): ?string
    {
        $leaseId = Str::random(32);
        $now = now();
        $claimed = self::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', $now)
            ->where(fn (Builder $q) => $q->whereNull('download_lease_until')->orWhere('download_lease_until', '<', $now))
            ->update([
                'download_lease_id' => $leaseId,
                'download_lease_until' => $now->copy()->addSeconds(self::DOWNLOAD_LEASE_SECONDS),
                'download_attempts' => DB::raw('download_attempts + 1'),
                'updated_at' => $now,
            ]);

        return $claimed === 1 ? $leaseId : null;
    }

    public function renewDownload(string $leaseId): void
    {
        self::query()->whereKey($this->getKey())->where('download_lease_id', $leaseId)
            ->update(['download_lease_until' => now()->addSeconds(self::DOWNLOAD_LEASE_SECONDS)]);
    }

    /**
     * An interrupted download frees the link at once (iPadOS may retry).
     */
    public function releaseDownload(string $leaseId): void
    {
        self::query()->whereKey($this->getKey())->where('download_lease_id', $leaseId)
            ->update(['download_lease_id' => null, 'download_lease_until' => null]);
    }

    /**
     * The whole .ipa was sent: the link is used. Only the download holding
     * the lease can do this, and only once.
     */
    public function markUsed(string $leaseId, ?string $ip, ?string $userAgent): bool
    {
        return self::query()->whereKey($this->getKey())
            ->where('download_lease_id', $leaseId)
            ->where('status', self::STATUS_ACTIVE)
            ->update([
                'status' => self::STATUS_USED,
                'used_at' => now(),
                'used_ip' => $ip,
                'used_user_agent' => $userAgent !== null ? Str::limit($userAgent, 490, '') : null,
                'download_lease_id' => null,
                'download_lease_until' => null,
                'updated_at' => now(),
            ]) === 1;
    }
}
