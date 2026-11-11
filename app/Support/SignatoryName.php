<?php

namespace App\Support;

use App\Models\DocumentSetting;

/**
 * Formats a signatory name for a PDF signature block, following the
 * "show degree" document setting. Names are typed with the Indonesian
 * convention -- prefix titles before the name, degrees after a comma
 * ("Dr. Ir. Budi Santoso, S.Kom., M.M.") -- so with degrees off the
 * prefix titles and everything after the first comma are dropped.
 * Names of HRIS users follow that person's own setting instead.
 */
class SignatoryName
{
    public static function format(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return $name;
        }

        // A name that belongs to an HRIS user follows that person's own
        // "show degree" choice (My Profile); anyone else follows the
        // document setting.
        $user = PersonName::findUser($name);
        if ($user) {
            return PersonName::display($user['name'], $user['show_degree']);
        }

        if (self::showDegree()) {
            return $name;
        }

        return self::withoutDegree($name);
    }

    public static function withoutDegree(string $name): string
    {
        return PersonName::bare($name) ?: trim($name);
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
