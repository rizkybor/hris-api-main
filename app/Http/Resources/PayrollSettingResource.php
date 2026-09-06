<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attendance_exempt_roles_enabled' => (bool) $this->attendance_exempt_roles_enabled,
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'updated_at' => $this->updated_at,
        ];
    }
}
