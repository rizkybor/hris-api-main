<?php

namespace App\Http\Resources;

use App\Models\LicenseRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
}
