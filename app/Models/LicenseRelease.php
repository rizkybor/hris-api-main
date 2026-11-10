<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One version of a product in its version list (release history):
 * major.minor.patch with the new features, improvements and bug fixes.
 *
 * - patch (1.0.1): bug fixes;
 * - minor (1.1.0): new features and improvements;
 * - major (2.0.0): a new major version. Lifetime licenses cover one major
 *   version and its updates (License::$updates_major); the next major is
 *   a paid upgrade for them.
 */
class LicenseRelease extends Model
{
    use SoftDeletes;

    public const VERSION_REGEX = '/^(0|[1-9]\d{0,3})\.(0|[1-9]\d{0,3})\.(0|[1-9]\d{0,3})$/';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    public const PLATFORMS_BOTH = 'both';

    public const PLATFORMS = [self::PLATFORMS_BOTH, License::PLATFORM_MAC, License::PLATFORM_IPAD];

    /**
     * Installers are stored on this private disk under
     * license-releases/{product}/{version}/.
     */
    public const FILE_DISK = 'local';

    /**
     * Largest installer upload in kilobytes (2 GB). The web server and PHP
     * (upload_max_filesize, post_max_size) must allow it too.
     */
    public const FILE_MAX_KB = 2097152;

    /**
     * Installer file types: the Mac disk image (and a zipped app).
     */
    public const FILE_EXTENSIONS = ['dmg', 'zip', 'pkg'];

    public const KIND_MAJOR = 'major';

    public const KIND_MINOR = 'minor';

    public const KIND_PATCH = 'patch';

    public const KIND_LABELS = [
        self::KIND_MAJOR => 'Major version',
        self::KIND_MINOR => 'Feature update',
        self::KIND_PATCH => 'Bug fix update',
    ];

    public const CHANGE_FEATURE = 'feature';

    public const CHANGE_IMPROVEMENT = 'improvement';

    public const CHANGE_FIX = 'fix';

    public const CHANGE_TYPES = [self::CHANGE_FEATURE, self::CHANGE_IMPROVEMENT, self::CHANGE_FIX];

    public const CHANGE_LABELS = [
        self::CHANGE_FEATURE => 'New',
        self::CHANGE_IMPROVEMENT => 'Improved',
        self::CHANGE_FIX => 'Fixed',
    ];

    protected $fillable = [
        'product_id',
        'version',
        'major',
        'minor',
        'patch',
        'build',
        'platforms',
        'title',
        'status',
        'released_at',
        'is_critical',
        'download_url',
        'file_path',
        'file_name',
        'file_size',
        'file_sha256',
        'file_uploaded_at',
        'changes',
        'notes',
    ];

    protected $casts = [
        'major' => 'integer',
        'minor' => 'integer',
        'patch' => 'integer',
        'build' => 'integer',
        'released_at' => 'date',
        'is_critical' => 'boolean',
        'file_size' => 'integer',
        'file_uploaded_at' => 'datetime',
        'changes' => 'array',
    ];

    /**
     * major / minor / patch always follow the version string.
     */
    protected static function booted(): void
    {
        static::saving(function (self $release) {
            [$release->major, $release->minor, $release->patch] = self::parse($release->version) ?? [0, 0, 0];
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LicenseProduct::class, 'product_id');
    }

    /**
     * "1.2.3" → [1, 2, 3]; "1.2" → [1, 2, 0]; anything else → null.
     *
     * @return array{int, int, int}|null
     */
    public static function parse(?string $version): ?array
    {
        if (! preg_match('/^\s*v?(\d{1,4})(?:\.(\d{1,4}))?(?:\.(\d{1,4}))?/i', (string) $version, $m)) {
            return null;
        }

        return [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)];
    }

    /**
     * -1, 0 or 1 like version_compare, for "major.minor.patch" strings.
     */
    public static function compare(?string $a, ?string $b): int
    {
        return (self::parse($a) ?? [0, 0, 0]) <=> (self::parse($b) ?? [0, 0, 0]);
    }

    public function scopeOrdered(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('major', $direction)->orderBy('minor', $direction)->orderBy('patch', $direction);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Releases for a platform ("mac" / "ipad"): its own and the shared ones.
     */
    public function scopeForPlatform(Builder $query, ?string $platform): Builder
    {
        return $platform ? $query->whereIn('platforms', [self::PLATFORMS_BOTH, $platform]) : $query;
    }

    /**
     * Newer than the given version.
     */
    public function scopeNewerThan(Builder $query, string $version): Builder
    {
        [$major, $minor, $patch] = self::parse($version) ?? [0, 0, 0];

        return $query->where(fn (Builder $q) => $q
            ->where('major', '>', $major)
            ->orWhere(fn (Builder $q) => $q->where('major', $major)->where('minor', '>', $minor))
            ->orWhere(fn (Builder $q) => $q->where('major', $major)->where('minor', $minor)->where('patch', '>', $patch)));
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    /**
     * A Mac (or shared) version needs its installer before it is published;
     * an iPad-only version is installed through Apple, not a file.
     */
    public function needsFile(): bool
    {
        return $this->platforms !== License::PLATFORM_IPAD;
    }

    public function kind(): string
    {
        return match (true) {
            $this->patch > 0 => self::KIND_PATCH,
            $this->minor > 0 => self::KIND_MINOR,
            default => self::KIND_MAJOR,
        };
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Number of changes per type, e.g. ['feature' => 3, 'improvement' => 0, 'fix' => 2].
     *
     * @return array<string, int>
     */
    public function changeCounts(): array
    {
        $counts = array_fill_keys(self::CHANGE_TYPES, 0);
        foreach ($this->getAttribute('changes') ?? [] as $change) {
            $type = $change['type'] ?? null;
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }
}
