<?php

namespace App\Support;

use App\Models\DocumentSetting;

/**
 * Formats a signatory name for a PDF signature block, following the
 * "show degree" document setting. Names are typed with the Indonesian
 * convention -- prefix titles before the name, degrees after a comma
 * ("Dr. Ir. Budi Santoso, S.Kom., M.M.") -- so with degrees off the
 * prefix titles and everything after the first comma are dropped.
 */
class SignatoryName
{
    private const PREFIX_TITLES = '(?:prof|dr|drs|dra|ir|drg)\.?';

    public static function format(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return $name;
        }

        if (self::showDegree()) {
            return $name;
        }

        return self::withoutDegree($name);
    }

    public static function withoutDegree(string $name): string
    {
        $bare = trim(explode(',', $name)[0]);
        $bare = preg_replace('/^(?:'.self::PREFIX_TITLES.'\s+)+/i', '', $bare);

        return trim(preg_replace('/\s+/', ' ', $bare)) ?: trim($name);
    }

    private static function showDegree(): bool
    {
        try {
            return DocumentSetting::current()->signatory_show_degree;
        } catch (\Throwable) {
            // Table not migrated yet -- keep names exactly as typed.
            return true;
        }
    }
}
