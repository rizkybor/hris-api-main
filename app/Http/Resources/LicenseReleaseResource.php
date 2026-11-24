<?php

namespace App\Http\Resources;

use App\Models\LicenseRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin LicenseRelease
 */
class LicenseReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'major' => $this->major,
            'minor' => $this->minor,
            'patch' => $this->patch,
            'build' => $this->build,
            'kind' => $this->kind(),
            'kind_label' => LicenseRelease::KIND_LABELS[$this->kind()],
            'platforms' => $this->platforms,
            'target_platforms' => $this->platforms === LicenseRelease::PLATFORMS_MULTI ? array_values($this->target_platforms ?? []) : null,
            'platform_list' => $this->platformList(),
            'android_min_sdk' => $this->android_min_sdk,
            'android_target_sdk' => $this->android_target_sdk,
            'title' => $this->title,
            'status' => $this->status,
            'released_at' => $this->released_at?->toDateString(),
            'is_critical' => $this->is_critical,
            'download_url' => $this->download_url,
            // The stored installer (download: GET …/releases/{id}/file).
            'file' => $this->hasFile() ? [
                'name' => $this->file_name,
                'size' => $this->file_size,
                'sha256' => $this->file_sha256,
                'uploaded_at' => $this->file_uploaded_at,
            ] : null,
            'needs_file' => $this->needsFile(),
            // The iPad installer (download: GET …/releases/{id}/ipa) with what
            // was read from it on upload: bundle id, version, build and the
            // provisioning profile (type, expiry, provisioned iPads).
            'ipa' => $this->hasIpa() ? [
                'name' => $this->ipa_name,
                'size' => $this->ipa_size,
                'sha256' => $this->ipa_sha256,
                'uploaded_at' => $this->ipa_uploaded_at,
                'bundle_id' => $this->ipa_meta['bundle_id'] ?? null,
                'short_version' => $this->ipa_meta['short_version'] ?? null,
                'build' => $this->ipa_meta['build'] ?? null,
                'minimum_os' => $this->ipa_meta['minimum_os'] ?? null,
                'profile' => $this->profile(),
                'warnings' => $this->ipa_meta['warnings'] ?? [],
            ] : null,
            'needs_ipa' => $this->needsIpa(),
            'install_links_active' => $this->install_links_active ?? null,
            'install_links_used' => $this->install_links_used ?? null,
            'changes' => array_values($this->resource->getAttribute('changes') ?? []),
            'change_counts' => $this->changeCounts(),
            'notes' => $this->notes,
            // Licenses that get this version: every running license, except
            // Lifetime licenses of an older major version (they need the upgrade).
            'licenses_covered' => $this->licenses_covered ?? null,
            'licenses_needing_upgrade' => $this->licenses_needing_upgrade ?? null,
            'devices_on_version' => $this->devices_on_version ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The stored profile, with "expired" recomputed (it was stored at upload).
     */
    private function profile(): ?array
    {
        $profile = $this->ipa_meta['profile'] ?? null;
        if (! is_array($profile)) {
            return null;
        }
        $profile['expired'] = ! empty($profile['expires_at']) && Carbon::parse($profile['expires_at'])->isPast();

        return $profile;
    }
}
