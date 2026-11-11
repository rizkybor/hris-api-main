<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Models\License;
use App\Models\LicenseProduct;
use App\Models\LicenseRelease;
use Illuminate\Support\Facades\Storage;

/**
 * What the apps ask about versions: the published version list, and
 * whether a newer version is available for this device and license.
 */
class LicenseReleaseService
{
    /**
     * Published releases for a platform, newest first.
     */
    public function history(string $productCode, ?string $platform): array
    {
        $product = $this->product($productCode);

        return [
            'product' => $product->code,
            'releases' => $product->releases()->with('product')->published()->forPlatform($platform)->ordered()->get()
                ->map(fn (LicenseRelease $release) => $this->publicRelease($release))->values()->all(),
        ];
    }

    /**
     * Update check: the newest version, the newest version the license
     * covers (Lifetime: its major version), and whether updating is
     * required (a critical release newer than the running version).
     */
    public function check(string $productCode, ?string $platform, string $version, ?string $key): array
    {
        $product = $this->product($productCode);
        $published = $product->releases()->published()->forPlatform($platform);

        $latest = (clone $published)->ordered()->first();
        $license = $key ? app(LicenseService::class)->findByKey($product, $key) : null;
        $covered = $license?->updates_major !== null
            ? (clone $published)->where('major', '<=', $license->updates_major)->ordered()->first()
            : $latest;

        $newer = fn (?LicenseRelease $release) => $release !== null && LicenseRelease::compare($release->version, $version) > 0;

        return [
            'current_version' => $version,
            'latest' => $latest ? $this->publicRelease($latest) : null,
            // The version this device should install (covered by the license).
            'update' => $newer($covered) ? $this->publicRelease($covered) : null,
            'update_available' => $newer($covered),
            // A newer major the license does not cover: a paid upgrade.
            'upgrade_available' => $newer($latest) && $latest !== null && $covered?->isNot($latest) === true
                ? $latest->version : null,
            'required' => (clone $published)->newerThan($version)->where('is_critical', true)->exists(),
            'updates_major' => $license?->updates_major,
        ];
    }

    /**
     * The installer of a published version, for a license key of the
     * product that is on, not expired, for the right platform, and covers
     * the version (Lifetime: its major version).
     *
     * @return array{0: LicenseRelease, 1: string} the release and its file path
     */
    public function installer(string $productCode, string $version, ?string $key): array
    {
        $product = $this->product($productCode);
        $release = $product->releases()->published()->where('version', $version)->first();
        if (! $release || ! $release->hasFile() || ! Storage::disk(LicenseRelease::FILE_DISK)->exists($release->file_path)) {
            throw new LicenseException('not_found', 'This version has no installer to download.', 404);
        }

        $license = $key ? app(LicenseService::class)->findByKey($product, $key) : null;
        if (! $license) {
            throw LicenseException::invalidKey();
        }
        if (! $license->is_active) {
            throw LicenseException::inactive();
        }
        if ($license->isExpired()) {
            throw LicenseException::expired();
        }
        // The stored installer is the Mac app (.dmg): a Mac key is needed.
        if ($license->platform !== License::PLATFORM_MAC) {
            throw LicenseException::wrongPlatform($license->platform);
        }
        if (! $license->coversVersion($release->version)) {
            throw new LicenseException('upgrade_required', "This license covers {$product->name} {$license->updates_major}.x. Upgrade it to download {$release->version}.", 403);
        }

        return [$release, $release->file_path];
    }

    private function product(string $code): LicenseProduct
    {
        $product = LicenseProduct::findByCode($code);
        if (! $product) {
            throw LicenseException::invalidProduct();
        }

        return $product;
    }

    private function publicRelease(LicenseRelease $release): array
    {
        return [
            'version' => $release->version,
            'build' => $release->build,
            'kind' => $release->kind(),
            'platforms' => $release->platforms,
            'title' => $release->title,
            'released_at' => $release->released_at?->toDateString(),
            'is_critical' => $release->is_critical,
            // The stored installer (needs a license key), else an outside link.
            'download_url' => $release->hasFile()
                ? url("/api/licenses/{$release->product?->code}/releases/{$release->version}/download")
                : $release->download_url,
            'file_size' => $release->file_size,
            'file_sha256' => $release->file_sha256,
            'changes' => array_values($release->getAttribute('changes') ?? []),
        ];
    }
}
