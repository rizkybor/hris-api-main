<?php

namespace App\Support;

/**
 * Maps a signatory name typed on a document to its scanned signature
 * (public/images/signatures). Matched loosely -- the degree after the
 * comma is ignored and only the leading name has to match -- since the
 * same person shows up as e.g. "Aldi PP, S.Ikom" on one document and
 * "Aldi Pratama Putra, S.Ikom" on another.
 */
class SignatureImage
{
    private const SIGNATURES = [
        'aldi' => 'aldi.png',
        'andy saputra' => 'andy.png',
        'rizky ajie' => 'rizky.png',
        'rizky aji' => 'rizky.png', // the account name is spelled "Aji"
    ];

    public static function pathFor(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        // Titles before the name and degrees after the comma are ignored,
        // so the signature shows whether or not the name carries them.
        $normalized = mb_strtolower(PersonName::bare($name));

        foreach (self::SIGNATURES as $prefix => $file) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.' ')) {
                $path = public_path('images/signatures/'.$file);

                return is_file($path) ? $path : null;
            }
        }

        return null;
    }
}
