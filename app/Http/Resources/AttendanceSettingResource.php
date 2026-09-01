<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'allow_weekend_check_in' => (bool) $this->allow_weekend_check_in,
            'office_latitude' => (float) $this->office_latitude,
            'office_longitude' => (float) $this->office_longitude,
            'office_radius_meters' => (int) $this->office_radius_meters,
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'updated_at' => $this->updated_at,
        ];
    }
}
