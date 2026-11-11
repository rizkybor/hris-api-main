<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Names are typed with the Indonesian convention: prefix titles before the
 * name, degrees after a comma ("Dr. Ir. Budi Santoso, S.Kom., M.M.").
 * The full name is stored as typed (users.name); how it is SHOWN follows
 * the person's own "show degree" setting (users.show_degree, default off,
 * set in My Profile).
 */
class PersonName
{
    private const PREFIX_TITLES = '(?:(?:prof|dr|drs|dra|ir|drg)\.?|hj?\.)';

    /** @var array<string, array{name: string, show_degree: bool}>|null bare name (lowercase) => user */
    private static ?array $usersByBareName = null;

    /** @var array<int, true>|null ids of users who show their degree */
    private static ?array $showDegreeIds = null;

    /** "Dr. Budi Santoso, S.Kom." → ["Dr. ", "Budi Santoso", ", S.Kom."] */
    public static function parts(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $comma = strpos($name, ',');
        $main = $comma === false ? $name : trim(substr($name, 0, $comma));
        $suffix = $comma === false ? '' : trim(substr($name, $comma + 1));

        preg_match('/^((?:'.self::PREFIX_TITLES.'\s+)*)/i', $main, $m);
        $prefix = $m[1] ?? '';
        $bare = trim(substr($main, strlen($prefix)));

        if ($bare === '') {
            // Nothing but titles: treat it all as the name.
            return ['', $main, $suffix === '' ? '' : ', '.$suffix];
        }

        return [$prefix, $bare, $suffix === '' ? '' : ', '.$suffix];
    }

    public static function bare(?string $name): string
    {
        return $name === null || trim($name) === '' ? (string) $name : self::parts($name)[1];
    }

    public static function display(?string $fullName, bool $showDegree): ?string
    {
        if ($fullName === null || trim($fullName) === '') {
            return $fullName;
        }

        return $showDegree ? trim(preg_replace('/\s+/', ' ', $fullName)) : self::bare($fullName);
    }

    /**
     * A name change sent without titles/degrees (e.g. from a form that
     * showed the display name) keeps the person's existing titles and
     * degrees; one that includes them replaces the whole name.
     */
    public static function mergeKeepingDegree(string $incoming, ?string $current): string
    {
        $incoming = trim(preg_replace('/\s+/', ' ', $incoming));
        if ($current === null || $current === '') {
            return $incoming;
        }
        [$inPrefix, , $inSuffix] = self::parts($incoming);
        if ($inPrefix !== '' || $inSuffix !== '') {
            return $incoming;
        }
        [$prefix, , $suffix] = self::parts($current);

        return trim($prefix.$incoming.$suffix);
    }

    public static function showsDegree(int $userId): bool
    {
        try {
            self::$showDegreeIds ??= DB::table('users')->where('show_degree', true)->pluck('id')->flip()->map(fn () => true)->all();
        } catch (\Throwable) {
            // users.show_degree not migrated yet: everyone shows without degrees.
            self::$showDegreeIds = [];
        }

        return isset(self::$showDegreeIds[$userId]);
    }

    /**
     * The HRIS user a typed name refers to (same bare name, any titles),
     * or null. Used for signatory names typed on documents.
     */
    public static function findUser(?string $name): ?array
    {
        if ($name === null || trim($name) === '') {
            return null;
        }
        if (self::$usersByBareName === null) {
            self::$usersByBareName = [];
            try {
                $rows = DB::table('users')->whereNull('deleted_at')->get(['name', 'show_degree']);
            } catch (\Throwable) {
                // users.show_degree not migrated yet.
                $rows = DB::table('users')->whereNull('deleted_at')->get(['name'])->each(fn ($r) => $r->show_degree = false);
            }
            foreach ($rows as $row) {
                $key = mb_strtolower(self::bare($row->name));
                // Two people with the same bare name: don't guess.
                self::$usersByBareName[$key] = isset(self::$usersByBareName[$key])
                    ? ['ambiguous' => true]
                    : ['name' => $row->name, 'show_degree' => (bool) $row->show_degree];
            }
        }
        $user = self::$usersByBareName[mb_strtolower(self::bare($name))] ?? null;

        return $user && ! isset($user['ambiguous']) ? $user : null;
    }

    /** Clears the per-request caches (after a name/setting change, and in tests). */
    public static function flush(): void
    {
        self::$usersByBareName = null;
        self::$showDegreeIds = null;
    }
}
