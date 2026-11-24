<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_licensed' => (bool) ($this->is_licensed ?? true),
            'key_prefix' => $this->key_prefix,
            // Preview only: the number the next Mac / iPad / Windows license gets.
            'next_sequences' => [
                'mac' => (int) $this->mac_license_sequence + 1,
                'ipad' => (int) $this->ipad_license_sequence + 1,
                'windows' => (int) $this->windows_license_sequence + 1,
            ],
            'description' => $this->description,
            'website_url' => $this->website_url,
            'icon_url' => $this->iconUrl(),
            'terms' => $this->terms,
            'terms_version' => $this->terms_version,
            'terms_effective_at' => $this->terms_effective_at?->toDateString(),
            'current_major' => $this->currentMajor(),
            'latest_version' => $this->latestRelease()?->version,
            'is_active' => $this->is_active,
            'licenses_count' => $this->licenses_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
