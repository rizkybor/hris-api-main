<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseActivationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Mac + iPad on one page: which license the device belongs to.
            'license_id' => $this->license_id,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            // One key per device: the key this device activated with.
            'license_key_id' => $this->license_key_id,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'activated_at' => $this->activated_at,
            'last_seen_at' => $this->last_seen_at,
        ];
    }
}
