<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Indonesian administrative regions (regions_* tables, filled by
 * `php artisan regions:import`). Codes are dotted and hierarchical:
 * 31 / 31.71 / 31.71.01 / 31.71.01.1001.
 */
class IndonesianRegions
{
    public const COUNTRY = 'Indonesia';

    public const CACHE_TTL = 86400;

    /**
     * level => [table, parent column, child level].
     */
    public const LEVELS = [
        'province' => ['regions_provinces', null],
        'regency' => ['regions_regencies', 'province_code'],
        'district' => ['regions_districts', 'regency_code'],
        'village' => ['regions_villages', 'district_code'],
    ];

    public static function isIndonesia(?string $country): bool
    {
        return strcasecmp(trim((string) $country), self::COUNTRY) === 0;
    }

    /**
     * [{code, name}] sorted by name; children of $parentCode for every level
     * below province. Cached for a day (the import clears the cache).
     */
    public static function list(string $level, ?string $parentCode = null): array
    {
        [$table, $parentColumn] = self::LEVELS[$level];

        return Cache::remember(self::cacheKey($level, $parentCode), self::CACHE_TTL, function () use ($table, $parentColumn, $parentCode) {
            return DB::table($table)
                ->when($parentColumn, fn ($query) => $query->where($parentColumn, $parentCode))
                ->orderBy('name')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($row) => ['code' => $row->code, 'name' => $row->name])
                ->all();
        });
    }

    public static function exists(string $level, string $code): bool
    {
        return DB::table(self::LEVELS[$level][0])->where('code', $code)->exists();
    }

    public static function cacheKey(string $level, ?string $parentCode): string
    {
        return 'regions:'.self::version().':'.$level.':'.($parentCode ?? 'all');
    }

    /**
     * Bumped by the import so every cached list is refreshed at once.
     */
    public static function version(): int
    {
        return (int) Cache::get('regions:version', 1);
    }

    public static function bumpVersion(): void
    {
        Cache::forever('regions:version', self::version() + 1);
    }

    /**
     * Validation of a full code chain: every code must exist and belong to
     * the code above it. Returns [field => message] for *_code fields.
     */
    public static function chainErrors(array $codes): array
    {
        $errors = [];
        $parent = null;
        $parentField = null;

        foreach (['province', 'regency', 'district', 'village'] as $level) {
            $field = $level.'_code';
            $code = $codes[$field] ?? null;
            if ($code === null || $code === '') {
                return $errors; // "required" is reported by the rules
            }

            [$table, $parentColumn] = self::LEVELS[$level];
            $row = DB::table($table)->where('code', $code)->first();
            if (! $row) {
                $errors[$field] = 'The selected '.self::label($level).' does not exist.';

                return $errors;
            }
            if ($parentColumn && $row->{$parentColumn} !== $parent) {
                $errors[$field] = 'The selected '.self::label($level).' is not in the selected '.self::label(substr($parentField, 0, -5)).'.';

                return $errors;
            }

            $parent = $code;
            $parentField = $field;
        }

        return $errors;
    }

    /**
     * Names for a valid code chain: [province, regency, district, village].
     */
    public static function names(array $codes): array
    {
        $names = [];
        foreach (self::LEVELS as $level => [$table]) {
            $code = $codes[$level.'_code'] ?? null;
            $names[$level] = $code ? DB::table($table)->where('code', $code)->value('name') : null;
        }

        return $names;
    }

    /**
     * The full chain from a village code (e.g. for the CLI).
     */
    public static function chainFromVillage(string $villageCode): array
    {
        $parts = explode('.', $villageCode);

        return [
            'province_code' => $parts[0] ?? null,
            'regency_code' => isset($parts[1]) ? $parts[0].'.'.$parts[1] : null,
            'district_code' => isset($parts[2]) ? implode('.', array_slice($parts, 0, 3)) : null,
            'village_code' => isset($parts[3]) ? $villageCode : null,
        ];
    }

    public static function label(string $level): string
    {
        return match ($level) {
            'province' => 'province',
            'regency' => 'city / regency',
            'district' => 'district',
            'village' => 'village',
            default => $level,
        };
    }
}
