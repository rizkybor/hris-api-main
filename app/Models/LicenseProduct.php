<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An app sold through the License Manager (OCCA, ...).
 * "code" is the slug used in the public API path and the admin URLs.
 */
class LicenseProduct extends Model
{
    use HasFactory;

    /**
     * Lowercase letters, digits and single hyphens, e.g. "map-tools-2".
     */
    public const CODE_REGEX = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * 1-6 uppercase letters, e.g. "OC" (keys look like OC32-XXXX-...).
     */
    public const KEY_PREFIX_REGEX = '/^[A-Z]{1,6}$/';

    public const OCCA = 'occa';

    /**
     * Icons live on the "public" disk under this folder.
     */
    public const ICON_DISK = 'public';

    public const ICON_DIRECTORY = 'license-products';

    protected $fillable = [
        'code',
        'name',
        'key_prefix',
        'mac_license_sequence',
        'ipad_license_sequence',
        'description',
        'icon_path',
        'terms',
        'terms_version',
        'terms_effective_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mac_license_sequence' => 'integer',
        'ipad_license_sequence' => 'integer',
        'terms_effective_at' => 'date',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'product_id');
    }

    /**
     * Full URL of the app icon, or null. asset() (the codebase's pattern for
     * the public disk) follows the host of the current request, so the URL
     * also works when APP_URL has no port (local php artisan serve).
     */
    public function iconUrl(): ?string
    {
        return $this->icon_path ? asset('storage/'.$this->icon_path) : null;
    }

    public static function findByCode(?string $code): ?self
    {
        return $code === null ? null : self::query()->where('code', strtolower(trim($code)))->first();
    }

    public static function findByCodeOrFail(string $code): self
    {
        return self::query()->where('code', strtolower(trim($code)))->firstOrFail();
    }

    /**
     * Counter column of a platform's license numbers (each platform has its
     * own 0001-9999 sequence).
     */
    public static function sequenceColumn(string $platform): string
    {
        return $platform.'_license_sequence';
    }
}
