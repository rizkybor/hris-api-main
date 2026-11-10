<?php

namespace App\DTOs;

use App\Models\License;
use App\Support\IndonesianRegions;
use Illuminate\Support\Carbon;

class LicenseDto
{
    public const DURATION_14_DAYS = '14_days';

    public const DURATION_30_DAYS = '30_days';

    public const DURATION_6_MONTHS = '6_months';

    public const DURATION_1_YEAR = '1_year';

    public const DURATION_CUSTOM = 'custom';

    public const DURATIONS = [self::DURATION_14_DAYS, self::DURATION_30_DAYS, self::DURATION_6_MONTHS, self::DURATION_1_YEAR, self::DURATION_CUSTOM];

    public function __construct(
        public readonly string $licensee_name,
        public readonly ?string $licensee_email,
        public readonly string $type,
        public readonly ?Carbon $starts_at,
        public readonly ?Carbon $expires_at,
        public readonly int $max_devices,
        public readonly bool $is_active,
        public readonly ?string $notes = null,
        public readonly string $plan = License::PLAN_PROFESSIONAL,
        /** Licensee details (phone, location, ... see License::*_FIELDS). */
        public readonly array $profile = [],
        /** Set on create only; fixed afterwards (null on update). */
        public readonly ?string $platform = null,
    ) {}

    /**
     * The key is not part of the DTO: it is generated on create and
     * immutable afterwards.
     */
    public function toArray(): array
    {
        return array_merge($this->profile, $this->platform ? ['platform' => $this->platform] : [], [
            'licensee_name' => $this->licensee_name,
            'licensee_email' => $this->licensee_email,
            'plan' => $this->plan,
            'type' => $this->type,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'max_devices' => $this->max_devices,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
        ]);
    }

    public static function fromArray(array $data): self
    {
        $type = $data['type'];
        $plan = $data['plan'] ?? License::PLAN_PROFESSIONAL;
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();

        $expiresAt = $type === License::TYPE_LIFETIME
            ? null
            : self::resolveExpiry($data['duration'] ?? null, $data['expires_at'] ?? null, $startsAt);

        return new self(
            licensee_name: $data['licensee_name'],
            licensee_email: $data['licensee_email'] ?? null,
            type: $type,
            starts_at: $startsAt,
            expires_at: $expiresAt,
            max_devices: (int) ($data['max_devices'] ?? 2),
            is_active: (bool) ($data['is_active'] ?? true),
            notes: $data['notes'] ?? null,
            plan: $plan,
            profile: self::profile($data, [], $plan, defaultCountry: true),
            platform: $data['platform'] ?? License::PLATFORM_MAC,
        );
    }

    public static function fromArrayForUpdate(array $data, License $existing): self
    {
        $type = $data['type'] ?? $existing->type;
        $startsAt = array_key_exists('starts_at', $data)
            ? ($data['starts_at'] ? Carbon::parse($data['starts_at']) : null)
            : $existing->starts_at;

        if ($type === License::TYPE_LIFETIME) {
            $expiresAt = null;
        } elseif (! empty($data['duration'])) {
            // A preset on update counts from today (back-office contract).
            $expiresAt = self::resolveExpiry($data['duration'], $data['expires_at'] ?? null, now());
        } elseif (! empty($data['expires_at'])) {
            $expiresAt = Carbon::parse($data['expires_at']);
        } else {
            $expiresAt = $existing->expires_at;
        }

        // A term license that gets an expiry but never had a start (e.g. a
        // lifetime license switched to term) starts today.
        if ($type === License::TYPE_TERM && $startsAt === null && $expiresAt !== null) {
            $startsAt = now();
        }

        $plan = $data['plan'] ?? $existing->plan;

        return new self(
            licensee_name: $data['licensee_name'] ?? $existing->licensee_name,
            licensee_email: array_key_exists('licensee_email', $data) ? $data['licensee_email'] : $existing->licensee_email,
            type: $type,
            starts_at: $startsAt,
            expires_at: $expiresAt,
            max_devices: (int) ($data['max_devices'] ?? $existing->max_devices),
            is_active: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $existing->is_active,
            notes: array_key_exists('notes', $data) ? $data['notes'] : $existing->notes,
            plan: $plan,
            profile: self::profile($data, $existing->only(self::profileFields()), $plan, defaultCountry: $existing->category() !== License::categoryForPlan($plan)),
        );
    }

    /**
     * Licensee details for the plan's category: sent values win over the
     * stored ones, blank strings become null, the other category's own
     * fields are cleared, and country defaults to Indonesia on create and
     * on a category change (a plain update never back-fills it).
     */
    private static function profile(array $data, array $existing, string $plan, bool $defaultCountry): array
    {
        $category = License::categoryForPlan($plan);
        $profile = [];

        foreach (self::profileFields() as $field) {
            $value = array_key_exists($field, $data) ? $data[$field] : ($existing[$field] ?? null);
            $profile[$field] = is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
        }

        foreach (License::otherCategoryFields($category) as $field) {
            $profile[$field] = null;
        }

        if ($defaultCountry) {
            $profile['country'] ??= License::DEFAULT_COUNTRY;
        }

        // Indonesia: the region codes are the source of truth, the names
        // come from them (only once the full chain is known, so older rows
        // without codes keep their names). Elsewhere there are no codes and
        // no village.
        if (IndonesianRegions::isIndonesia($profile['country'])) {
            if ($profile['village_code'] !== null) {
                $names = IndonesianRegions::names($profile);
                foreach ($names as $level => $name) {
                    $profile[$level] = $name ?? $profile[$level];
                }
            }
        } elseif ($profile['country'] !== null) {
            foreach (License::REGION_CODE_FIELDS as $field) {
                $profile[$field] = null;
            }
            $profile['village'] = null;
        }
        if ($profile['member_count'] !== null) {
            $profile['member_count'] = (int) $profile['member_count'];
        }

        return $profile;
    }

    private static function profileFields(): array
    {
        return array_merge(['phone'], License::LOCATION_FIELDS, License::INDIVIDUAL_FIELDS, License::ORGANIZATION_FIELDS);
    }

    private static function resolveExpiry(?string $duration, ?string $customExpiresAt, Carbon $base): ?Carbon
    {
        return match ($duration) {
            self::DURATION_14_DAYS => $base->copy()->addDays(14),
            self::DURATION_30_DAYS => $base->copy()->addDays(30),
            self::DURATION_6_MONTHS => $base->copy()->addMonthsNoOverflow(6),
            self::DURATION_1_YEAR => $base->copy()->addYearNoOverflow(),
            self::DURATION_CUSTOM => $customExpiresAt ? Carbon::parse($customExpiresAt) : null,
            default => $customExpiresAt ? Carbon::parse($customExpiresAt) : null,
        };
    }
}
