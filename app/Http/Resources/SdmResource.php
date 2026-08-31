<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SdmResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sdm_field_id' => $this->sdm_field_id,
            'sdm_field_name' => $this->field?->name,
            'productive_hours_per_month' => $this->productive_hours_per_month !== null ? (float) $this->productive_hours_per_month : null,
            'sdm_component' => $this->sdm_component,
            'capacity_target' => $this->capacity_target,
            'actual' => (float) (string) $this->actual,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}