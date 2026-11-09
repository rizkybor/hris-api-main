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
            'key_prefix' => $this->key_prefix,
            // Preview only: the number the next license of this product gets.
            'next_sequence' => (int) $this->license_sequence + 1,
            'description' => $this->description,
            'icon_url' => $this->iconUrl(),
            'terms' => $this->terms,
            'terms_version' => $this->terms_version,
            'terms_effective_at' => $this->terms_effective_at?->toDateString(),
            'is_active' => $this->is_active,
            'licenses_count' => $this->licenses_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
