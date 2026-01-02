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
            'sdm_component' => $this->sdm_component,
            'metrik' => $this->metrik,
            'capacity_target' => $this->capacity_target,
            'budget' => (float) (string) $this->budget,
            'actual' => (float) (string) $this->actual,
            'rag_status' => $this->status_rag,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}