<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * "both" is Mac + iPad (the apps that share a version line); Windows
     * versions are their own rows, since OCCA for Windows has its own
     * release cadence and installer.
     */
    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_IOS = 'ios';

    /**
     * One version for several platforms, listed in target_platforms.
     */
    public const PLATFORMS_MULTI = 'multi';

    public const PLATFORMS = [
        self::PLATFORMS_BOTH, License::PLATFORM_MAC, License::PLATFORM_IPAD, License::PLATFORM_WINDOWS,
        self::PLATFORM_ANDROID, self::PLATFORM_IOS, self::PLATFORMS_MULTI,
    ];

    /**
     * Platforms a multiplatform version can list.
     */
    public const TARGET_PLATFORMS = [
        License::PLATFORM_MAC, License::PLATFORM_IPAD, License::PLATFORM_WINDOWS, self::PLATFORM_ANDROID, self::PLATFORM_IOS,
    ];

    /**
     * Android API levels accepted for minimum / target SDK.
     */
    public const ANDROID_SDK_MIN = 16;

    public const ANDROID_SDK_MAX = 40;

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
     * Installer file types stored in the "file" slot: the Mac disk image
     * (and a zipped app or .pkg) for Mac / shared versions, the MSI or
     * MSIX installer for Windows versions.
     */
    public const FILE_EXTENSIONS = ['dmg', 'zip', 'pkg', 'msi', 'msix', 'exe'];

    public const WINDOWS_FILE_EXTENSIONS = ['msi', 'msix', 'exe'];

    public const MAC_FILE_EXTENSIONS = ['dmg', 'zip', 'pkg'];

    public const ANDROID_FILE_EXTENSIONS = ['apk', 'aab'];

    /**
     * The iPad installer: an Ad Hoc signed .ipa, installed over the air
     * through single-use install links (LicenseInstallLink).
     */
    public const IPA_EXTENSIONS = ['ipa'];

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
        'target_platforms',
        'android_min_sdk',
        'android_target_sdk',
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
        'ipa_path',
        'ipa_name',
        'ipa_size',
        'ipa_sha256',
        'ipa_meta',
        'ipa_uploaded_at',
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
        'ipa_size' => 'integer',
        'ipa_meta' => 'array',
        'target_platforms' => 'array',
        'android_min_sdk' => 'integer',
        'android_target_sdk' => 'integer',
        'ipa_uploaded_at' => 'datetime',
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

    public function installLinks(): HasMany
    {
        return $this->hasMany(LicenseInstallLink::class, 'release_id');
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
     * Releases for a platform ("mac" / "ipad" / "windows"): its own and,
     * for Mac and iPad, the shared ("both") ones.
     */
    public function scopeForPlatform(Builder $query, ?string $platform): Builder
    {
        if ($platform === null || $platform === '') {
            return $query;
        }
        // Mac and iPad include the shared Mac + iPad line; every platform
        // includes the multiplatform versions that list it.
        $own = in_array($platform, [License::PLATFORM_MAC, License::PLATFORM_IPAD], true)
            ? [self::PLATFORMS_BOTH, $platform]
            : [$platform];

        return $query->where(fn (Builder $q) => $q
            ->whereIn('platforms', $own)
            ->orWhere(fn (Builder $m) => $m->where('platforms', self::PLATFORMS_MULTI)->whereJsonContains('target_platforms', $platform)));
    }

    /**
     * The platforms this version runs on ("both" = Mac and iPad).
     *
     * @return array<int, string>
     */
    public function platformList(): array
    {
        return match ($this->platforms) {
            self::PLATFORMS_BOTH => [License::PLATFORM_MAC, License::PLATFORM_IPAD],
            self::PLATFORMS_MULTI => array_values($this->target_platforms ?? []),
            default => [$this->platforms],
        };
    }

    public function isMultiplatform(): bool
    {
        return $this->platforms === self::PLATFORMS_MULTI;
    }

    /**
     * Android settings apply when Android is one of its platforms.
     */
    public function hasAndroid(): bool
    {
        return in_array(self::PLATFORM_ANDROID, $this->platformList(), true);
    }

    /**
     * Allowed extensions for the installer file slot: the Mac disk image,
     * the Windows installer, the Android package, or for a multiplatform
     * version any of them (one file, e.g. a .zip bundle).
     *
     * @return array<int, string>
     */
    public function fileExtensions(): array
    {
        return match ($this->platforms) {
            License::PLATFORM_WINDOWS => self::WINDOWS_FILE_EXTENSIONS,
            self::PLATFORM_ANDROID => self::ANDROID_FILE_EXTENSIONS,
            self::PLATFORMS_MULTI => array_values(array_unique(array_merge(self::MAC_FILE_EXTENSIONS, self::WINDOWS_FILE_EXTENSIONS, self::ANDROID_FILE_EXTENSIONS))),
            default => self::MAC_FILE_EXTENSIONS,
        };
    }

    /**
     * Whether the stored installer file is for a license of this platform
     * (the Mac .dmg for Mac keys, the .msi for Windows keys; a
     * multiplatform file for any platform it lists).
     */
    public function fileServesPlatform(string $platform): bool
    {
        return match ($this->platforms) {
            self::PLATFORMS_MULTI => in_array($platform, $this->platformList(), true),
            License::PLATFORM_WINDOWS => $platform === License::PLATFORM_WINDOWS,
            License::PLATFORM_MAC, self::PLATFORMS_BOTH => $platform === License::PLATFORM_MAC,
            default => false,
        };
    }

    public function isWindows(): bool
    {
        return $this->platforms === License::PLATFORM_WINDOWS;
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
     * A Mac (or shared) version needs its Mac installer, a Windows version
     * its MSI / MSIX, an Android version its APK / AAB, before it is
     * published. A multiplatform version has no required installer (one
     * file can't cover every platform it lists).
     */
    public function needsFile(): bool
    {
        return in_array($this->platforms, [self::PLATFORMS_BOTH, License::PLATFORM_MAC, License::PLATFORM_WINDOWS, self::PLATFORM_ANDROID], true);
    }

    public function hasIpa(): bool
    {
        return filled($this->ipa_path);
    }

    /**
     * An iPad (or shared) version needs its iPad installer (.ipa) before
     * it is published.
     */
    public function needsIpa(): bool
    {
        return in_array($this->platforms, [self::PLATFORMS_BOTH, License::PLATFORM_IPAD, self::PLATFORM_IOS], true);
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
