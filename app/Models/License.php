<?php

namespace App\Models;

use App\Support\IndonesianRegions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class License extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_LIFETIME = 'lifetime';

    public const TYPE_TERM = 'term';

    public const TYPES = [self::TYPE_LIFETIME, self::TYPE_TERM];

    /**
     * Stored plans. Starter (free) is the app's mode without a key, so it is
     * never stored as a license.
     */
    public const PLAN_PROFESSIONAL = 'professional';

    /**
     * Time-limited individual license; always a term license, never lifetime.
     */
    public const PLAN_TRIAL = 'trial';

    /**
     * One-time payment individual license; always a lifetime license, never
     * term (the mirror of the trial rule). Was "enterprise" before
     * 2026_11_09_000006.
     */
    public const PLAN_LIFETIME = 'lifetime';

    public const PLAN_ORGANIZATION = 'organization';

    public const PLANS = [self::PLAN_PROFESSIONAL, self::PLAN_TRIAL, self::PLAN_LIFETIME, self::PLAN_ORGANIZATION];

    /**
     * The license type follows the plan: only the Lifetime plan is
     * lifetime; every other plan is a term license.
     */
    public const TERM_ONLY_PLANS = [self::PLAN_TRIAL, self::PLAN_PROFESSIONAL, self::PLAN_ORGANIZATION];

    public const PLAN_LABELS = [
        self::PLAN_PROFESSIONAL => 'Professional',
        self::PLAN_TRIAL => 'Trial',
        self::PLAN_LIFETIME => 'Lifetime',
        self::PLAN_ORGANIZATION => 'Organization',
    ];

    /**
     * The device platform a license is for, fixed at creation: a Mac key
     * only activates on a Mac, an iPad key only on an iPad.
     */
    public const PLATFORM_MAC = 'mac';

    public const PLATFORM_IPAD = 'ipad';

    public const PLATFORMS = [self::PLATFORM_MAC, self::PLATFORM_IPAD];

    /**
     * Create-only choice: one Mac and one iPad license generated together
     * (same licensee, plan and period), linked by bundle_id.
     */
    public const PLATFORM_BOTH = 'both';

    public const CREATE_PLATFORMS = [self::PLATFORM_MAC, self::PLATFORM_IPAD, self::PLATFORM_BOTH];

    public const PLATFORM_LABELS = [
        self::PLATFORM_MAC => 'Mac',
        self::PLATFORM_IPAD => 'iPad',
    ];

    /**
     * Plans sold per platform: iPad has no Trial (no free plan there).
     */
    public const PLATFORM_PLANS = [
        self::PLATFORM_MAC => [self::PLAN_TRIAL, self::PLAN_PROFESSIONAL, self::PLAN_LIFETIME, self::PLAN_ORGANIZATION],
        self::PLATFORM_IPAD => [self::PLAN_PROFESSIONAL, self::PLAN_LIFETIME, self::PLAN_ORGANIZATION],
    ];

    public const CATEGORY_INDIVIDUAL = 'individual';

    public const CATEGORY_ORGANIZATION = 'organization';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_EXPIRED = 'expired';

    /**
     * Active, term, and EXPIRING_SOON_DAYS or fewer days left.
     */
    public const STATUS_EXPIRING_SOON = 'expiring_soon';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_EXPIRED, self::STATUS_EXPIRING_SOON];

    public const EXPIRING_SOON_DAYS = 14;

    public const ORGANIZATION_TYPES = ['team', 'community', 'club', 'federation'];

    public const ORGANIZATION_TYPE_LABELS = [
        'team' => 'Team',
        'community' => 'Community',
        'club' => 'Club',
        'federation' => 'Federation',
    ];

    public const DEFAULT_COUNTRY = 'Indonesia';

    /**
     * Location (both categories). For Indonesia the region codes are the
     * source of truth and the names are filled from them; elsewhere the
     * names are free text and the codes / village stay empty. "regency" is
     * the city / regency (Kota/Kabupaten).
     */
    public const LOCATION_FIELDS = [
        'country', 'province', 'regency', 'district', 'village', 'postal_code',
        'province_code', 'regency_code', 'district_code', 'village_code', 'address',
    ];

    public const REGION_CODE_FIELDS = ['province_code', 'regency_code', 'district_code', 'village_code'];

    /**
     * Licensee details per category. Shared fields are kept when the
     * category changes; the other category's own fields are cleared.
     */
    public const SHARED_FIELDS = ['licensee_name', 'licensee_email', 'phone', ...self::LOCATION_FIELDS];

    public const INDIVIDUAL_FIELDS = ['occupation', 'affiliation'];

    public const ORGANIZATION_FIELDS = ['organization_type', 'contact_name', 'contact_position', 'website', 'tax_id', 'member_count'];

    /**
     * Required per category, apart from the location (see
     * requiredLocationFields()).
     */
    public const REQUIRED_FIELDS = [
        self::CATEGORY_INDIVIDUAL => ['licensee_name', 'licensee_email', 'phone'],
        self::CATEGORY_ORGANIZATION => ['licensee_name', 'organization_type', 'contact_name', 'licensee_email', 'phone'],
    ];

    /**
     * Required location fields: Indonesia needs the full region code chain
     * and a postal code; elsewhere state / province, city and postal code.
     * Organizations also need the street address.
     */
    public static function requiredLocationFields(string $category, ?string $country): array
    {
        $fields = IndonesianRegions::isIndonesia($country)
            ? ['country', 'province_code', 'regency_code', 'district_code', 'village_code', 'postal_code']
            : ['country', 'province', 'regency', 'postal_code'];

        if ($category === self::CATEGORY_ORGANIZATION) {
            $fields[] = 'address';
        }

        return $fields;
    }

    public static function requiredFields(string $category, ?string $country): array
    {
        return array_merge(self::REQUIRED_FIELDS[$category], self::requiredLocationFields($category, $country));
    }

    /**
     * Random key groups: A-Z and 2-9 without the easily confused 0/O, 1/I/L.
     */
    public const KEY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The key's two-digit code ({prefix}{code}-...) is platform digit + plan
     * digit: Mac 31 Trial, 32 Professional, 33 Lifetime, 34 Organization;
     * iPad 52 Professional, 53 Lifetime, 54 Organization. A key never
     * changes, so the code is the platform and plan at creation.
     */
    public const PLATFORM_CODES = [
        self::PLATFORM_MAC => '3',
        self::PLATFORM_IPAD => '5',
    ];

    public const PLAN_DIGITS = [
        self::PLAN_TRIAL => '1',
        self::PLAN_PROFESSIONAL => '2',
        self::PLAN_LIFETIME => '3',
        self::PLAN_ORGANIZATION => '4',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(array_merge(
                ['key', 'plan', 'platform', 'type', 'starts_at', 'expires_at', 'max_devices', 'is_active', 'notes'],
                self::SHARED_FIELDS,
                self::INDIVIDUAL_FIELDS,
                self::ORGANIZATION_FIELDS,
            ))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('License Manager');
    }

    protected $fillable = [
        'product_id',
        'key',
        'key_mode',
        'sequence',
        'licensee_name',
        'licensee_email',
        'phone',
        'province',
        'regency',
        'district',
        'village',
        'postal_code',
        'province_code',
        'regency_code',
        'district_code',
        'village_code',
        'country',
        'occupation',
        'affiliation',
        'organization_type',
        'contact_name',
        'contact_position',
        'address',
        'website',
        'tax_id',
        'member_count',
        'plan',
        'platform',
        'bundle_id',
        'type',
        'updates_major',
        'starts_at',
        'expires_at',
        'max_devices',
        'is_active',
        'notes',
        'terms_version',
        'last_activated_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_activated_at' => 'datetime',
        'max_devices' => 'integer',
        'updates_major' => 'integer',
        'sequence' => 'integer',
        'member_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(LicenseProduct::class, 'product_id');
    }

    public function scopeForProduct(Builder $query, LicenseProduct|int $product): Builder
    {
        return $query->where('product_id', $product instanceof LicenseProduct ? $product->id : $product);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    /**
     * How the license's key(s) work: one key shared by up to max_devices
     * devices, or one unique key per device (license_keys), where a key
     * that was used and released never works again.
     */
    public const KEY_MODE_SHARED = 'shared';

    public const KEY_MODE_PER_DEVICE = 'per_device';

    public const KEY_MODES = [self::KEY_MODE_SHARED, self::KEY_MODE_PER_DEVICE];

    public const KEY_MODE_LABELS = [
        self::KEY_MODE_SHARED => 'One shared key',
        self::KEY_MODE_PER_DEVICE => 'One key per device',
    ];

    /** The per-device keys, in order (key 1 is the license's own key). */
    public function keys(): HasMany
    {
        return $this->hasMany(LicenseKey::class)->orderBy('position');
    }

    public function isPerDevice(): bool
    {
        return $this->key_mode === self::KEY_MODE_PER_DEVICE;
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class);
    }

    /**
     * Every license generated together with this one (Mac + iPad),
     * including itself; see bundleSiblings() for the others.
     */
    public function bundleMembers(): HasMany
    {
        return $this->hasMany(self::class, 'bundle_id', 'bundle_id')->orderBy('platform');
    }

    /**
     * The other license(s) of the bundle (empty when not bundled).
     */
    public function bundleSiblings(): Collection
    {
        if (! $this->bundle_id) {
            return collect();
        }

        return $this->bundleMembers->reject(fn (self $member) => $member->is($this))->values();
    }

    /**
     * The platforms a create request stands for ("both" = Mac and iPad).
     *
     * @return list<string>
     */
    public static function platformsFor(?string $choice): array
    {
        return $choice === self::PLATFORM_BOTH ? self::PLATFORMS : [$choice ?: self::PLATFORM_MAC];
    }

    /**
     * Highest sequence per product and platform: the last key group is always
     * 4 digits, so an OCCA key is always 20 characters (OC32-XXXX-XXXX-XXXX-0001),
     * the length the app accepts.
     */
    public const MAX_SEQUENCE = 9999;

    /**
     * A new, unused key: {prefix}{platform}{plan}-XXXX-XXXX-XXXX-{sequence},
     * e.g. OC32-7KQM-2XRT-9HZP-0001. The sequence is the license's number
     * within its product and platform (4 digits, 0001-9999); the three
     * random groups make the key hard to guess. Keys are unique across all
     * products.
     */
    /**
     * Another key of the same license: same head (prefix + platform/plan
     * code) and 4-digit license number as $baseKey, new random groups.
     * Used for the keys of a "one key per device" license.
     */
    public static function generateSiblingKey(string $baseKey): string
    {
        $head = substr($baseKey, 0, (int) strpos($baseKey, '-'));
        $tail = substr($baseKey, (int) strrpos($baseKey, '-') + 1);
        $alphabetLength = strlen(self::KEY_ALPHABET);

        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $group = '';
                for ($i = 0; $i < 4; $i++) {
                    $group .= self::KEY_ALPHABET[random_int(0, $alphabetLength - 1)];
                }
                $groups[] = $group;
            }
            $key = $head.'-'.implode('-', $groups).'-'.$tail;
        } while (self::withTrashed()->where('key', $key)->exists() || LicenseKey::where('key', $key)->exists());

        return $key;
    }

    public static function generateKey(string $prefix, string $platform, string $plan, int $sequence): string
    {
        $alphabetLength = strlen(self::KEY_ALPHABET);
        $head = strtoupper($prefix).self::PLATFORM_CODES[$platform].self::PLAN_DIGITS[$plan];
        $tail = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        do {
            $groups = [];
            for ($g = 0; $g < 3; $g++) {
                $group = '';
                for ($i = 0; $i < 4; $i++) {
                    $group .= self::KEY_ALPHABET[random_int(0, $alphabetLength - 1)];
                }
                $groups[] = $group;
            }
            $key = $head.'-'.implode('-', $groups).'-'.$tail;
        } while (self::withTrashed()->where('key', $key)->exists() || LicenseKey::where('key', $key)->exists());

        return $key;
    }

    /**
     * Keys are matched case-insensitively and ignoring surrounding spaces.
     */
    public static function normalizeKey(?string $key): string
    {
        return strtoupper(trim((string) $key));
    }

    public function isLifetime(): bool
    {
        return $this->type === self::TYPE_LIFETIME;
    }

    public function planLabel(): string
    {
        return self::PLAN_LABELS[$this->plan] ?? ucfirst((string) $this->plan);
    }

    /**
     * individual (professional, trial, lifetime) | organization.
     */
    public function category(): string
    {
        return self::categoryForPlan($this->plan);
    }

    /**
     * lifetime for the Lifetime plan, term for every other plan.
     */
    public static function planAllowedOn(?string $platform, ?string $plan): bool
    {
        return in_array($plan, self::PLATFORM_PLANS[$platform] ?? [], true);
    }

    /**
     * The platform an app reports on activation ("macOS", "iPadOS", ...)
     * as a license platform, or null when it is not a Mac or an iPad.
     * Older iPads report "iOS"; OCCA does not run on iPhone.
     */
    public static function platformFromDevice(?string $reported): ?string
    {
        $value = strtolower(preg_replace('/[^a-z]/i', '', (string) $reported));

        return match (true) {
            in_array($value, ['macos', 'mac', 'osx', 'macosx'], true) => self::PLATFORM_MAC,
            in_array($value, ['ipados', 'ipad', 'ios'], true) => self::PLATFORM_IPAD,
            default => null,
        };
    }

    /**
     * The major version a license gets updates for: Lifetime licenses cover
     * one major version (set at creation to the product's current major,
     * raised by a paid upgrade); every other plan gets every version while
     * it runs (null).
     */
    public static function updatesMajorFor(?string $plan, ?int $requested, LicenseProduct $product): ?int
    {
        if ($plan !== self::PLAN_LIFETIME) {
            return null;
        }

        return $requested ?: $product->currentMajor();
    }

    /**
     * Whether this license may use the given app version ("2.1.0").
     */
    public function coversVersion(?string $version): bool
    {
        $parsed = LicenseRelease::parse($version);

        return $this->updates_major === null || $parsed === null || $parsed[0] <= $this->updates_major;
    }

    /**
     * "OCCA 1.x and its updates" (Lifetime) or "Every version while active".
     */
    public function updatesLabel(): string
    {
        $name = $this->product?->name ?? 'App';

        return $this->updates_major === null
            ? 'Every version while active'
            : "{$name} {$this->updates_major}.x and its updates";
    }

    public function platformLabel(): string
    {
        return self::PLATFORM_LABELS[$this->platform] ?? ucfirst((string) $this->platform);
    }

    public static function typeForPlan(?string $plan): string
    {
        return $plan === self::PLAN_LIFETIME ? self::TYPE_LIFETIME : self::TYPE_TERM;
    }

    public static function categoryForPlan(?string $plan): string
    {
        return $plan === self::PLAN_ORGANIZATION
            ? self::CATEGORY_ORGANIZATION
            : self::CATEGORY_INDIVIDUAL;
    }

    /**
     * The category's own fields (not shared). Used to clear the other
     * category's fields when a license switches category.
     */
    public static function categoryOnlyFields(string $category): array
    {
        return $category === self::CATEGORY_ORGANIZATION ? self::ORGANIZATION_FIELDS : self::INDIVIDUAL_FIELDS;
    }

    public static function otherCategoryFields(string $category): array
    {
        return $category === self::CATEGORY_ORGANIZATION ? self::INDIVIDUAL_FIELDS : self::ORGANIZATION_FIELDS;
    }

    /**
     * One line: street, village, district, city / regency, province,
     * postal code, country (empty parts left out).
     */
    public function fullAddress(): ?string
    {
        $parts = array_filter([
            $this->address,
            $this->village,
            $this->district,
            $this->regency,
            trim(($this->province ?? '').' '.($this->postal_code ?? '')),
            $this->country,
        ], fn ($part) => $part !== null && trim((string) $part) !== '');

        return $parts ? implode(', ', array_map('trim', $parts)) : null;
    }

    public function organizationTypeLabel(): ?string
    {
        return $this->organization_type ? (self::ORGANIZATION_TYPE_LABELS[$this->organization_type] ?? ucfirst($this->organization_type)) : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Start of the active period; falls back to created_at for rows that
     * never got a starts_at.
     */
    public function periodStart(): ?Carbon
    {
        return $this->starts_at ?? $this->created_at;
    }

    /**
     * Total days from starts_at to expires_at (partial days count as a
     * day); null for lifetime.
     */
    public function durationDays(): ?int
    {
        $start = $this->periodStart();
        if ($this->expires_at === null || $start === null) {
            return null;
        }

        return max(0, (int) ceil(($this->expires_at->getTimestamp() - $start->getTimestamp()) / 86400));
    }

    /**
     * Whole days left, rounded up; 0 once expired; null for lifetime.
     */
    public function remainingDays(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return max(0, (int) ceil(($this->expires_at->getTimestamp() - now()->getTimestamp()) / 86400));
    }

    /**
     * Share of the active period already used, 0-100 (one decimal); null
     * for lifetime.
     */
    public function elapsedPercent(): ?float
    {
        $start = $this->periodStart();
        if ($this->expires_at === null || $start === null) {
            return null;
        }

        $total = $this->expires_at->getTimestamp() - $start->getTimestamp();
        if ($total <= 0) {
            return 100.0;
        }

        $elapsed = now()->getTimestamp() - $start->getTimestamp();

        return round(min(100, max(0, $elapsed / $total * 100)), 1);
    }

    public function isExpiringSoon(): bool
    {
        return $this->is_active
            && $this->type === self::TYPE_TERM
            && $this->expires_at !== null
            && ! $this->isExpired()
            && $this->expires_at->lte(now()->addDays(self::EXPIRING_SOON_DAYS));
    }

    /**
     * active | inactive | expired | expiring_soon (inactive wins over
     * expired, expired over expiring_soon).
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->isExpiringSoon() ? self::STATUS_EXPIRING_SOON : self::STATUS_ACTIVE;
    }

    /**
     * Latest sign of life from the app: the newest device check-in or the
     * last activation.
     */
    public function lastActivityAt(): ?Carbon
    {
        $lastSeen = $this->activations_max_last_seen_at ?? null;
        if ($lastSeen === null && $this->relationLoaded('activations')) {
            $lastSeen = $this->activations->max('last_seen_at');
        }

        $candidates = array_filter([
            $lastSeen ? Carbon::parse($lastSeen) : null,
            $this->last_activated_at,
        ]);

        return $candidates ? max($candidates) : null;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('key', 'like', '%'.$search.'%')
                ->orWhereHas('keys', fn ($keys) => $keys->where('key', 'like', '%'.$search.'%'))
                ->orWhere('licensee_name', 'like', '%'.$search.'%')
                ->orWhere('licensee_email', 'like', '%'.$search.'%')
                ->orWhere('contact_name', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhere('regency', 'like', '%'.$search.'%');
        });
    }

    /**
     * Filter by status. "inactive" = switched off; "expired" = a term license
     * past its expiry (whether or not it is switched off); "expiring_soon" =
     * switched on, term, and 14 days or fewer left; "active" = switched on,
     * not expired and not expiring soon (matches the status field).
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            self::STATUS_INACTIVE => $query->where('is_active', false),
            // Expired regardless of the active toggle, so an expired license
            // that was also switched off still shows up here.
            self::STATUS_EXPIRED => $query->where('type', self::TYPE_TERM)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now()),
            self::STATUS_EXPIRING_SOON => $query->where('is_active', true)
                ->where('type', self::TYPE_TERM)
                ->where('expires_at', '>=', now())
                ->where('expires_at', '<=', now()->addDays(self::EXPIRING_SOON_DAYS)),
            self::STATUS_ACTIVE => $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('type', '!=', self::TYPE_TERM)
                        ->orWhere('expires_at', '>', now()->addDays(self::EXPIRING_SOON_DAYS));
                }),
            default => $query,
        };
    }
}
