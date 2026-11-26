<?php

namespace App\Support;

use App\Models\User;

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

    /** Who can sign, with the name shown on documents (one entry per scan). */
    private const PEOPLE = [
        ['key' => 'aldi', 'prefixes' => ['aldi'], 'file' => 'aldi.png', 'name' => 'Aldi Pratama Putra'],
        ['key' => 'andy', 'prefixes' => ['andy saputra'], 'file' => 'andy.png', 'name' => 'Andy Saputra'],
        ['key' => 'rizky', 'prefixes' => ['rizky ajie', 'rizky aji'], 'file' => 'rizky.png', 'name' => 'Rizky Ajie Kurniawan'],
    ];

    /**
     * The people with a signature on file (the scans include the company
     * stamp), for a "who signs" picker: their account name and job title
     * when an employee matches, and a small preview of the scan.
     *
     * @return list<array{key: string, name: string, title: ?string, preview: ?string}>
     */
    public static function signatories(): array
    {
        $users = User::query()->with('employeeProfile.jobInformation')->get(['id', 'name']);

        return collect(self::PEOPLE)
            ->filter(fn ($person) => is_file(public_path('images/signatures/'.$person['file'])))
            ->map(function ($person) use ($users) {
                $user = $users->first(function (User $user) use ($person) {
                    $bare = mb_strtolower(PersonName::bare($user->name));
                    foreach ($person['prefixes'] as $prefix) {
                        if ($bare === $prefix || str_starts_with($bare, $prefix.' ')) {
                            return true;
                        }
                    }

                    return false;
                });
                $path = public_path('images/signatures/'.$person['file']);

                return [
                    'key' => $person['key'],
                    'name' => $user?->name ?? $person['name'],
                    'title' => $user?->employeeProfile?->jobInformation?->job_title,
                    'preview' => 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)),
                ];
            })
            ->values()
            ->all();
    }

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
