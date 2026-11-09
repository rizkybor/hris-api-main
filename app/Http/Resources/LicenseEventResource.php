<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'label' => $this->label(),
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'ip' => $this->ip,
            'user_id' => $this->user_id,
            'user_name' => $this->user_id ? $this->user?->name : null,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
