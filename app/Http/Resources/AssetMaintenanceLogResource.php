<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMaintenanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'performed_at' => $this->performed_at,
            'description' => $this->description,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'next_due_date' => $this->next_due_date,
            'performed_by' => new UserResource($this->whenLoaded('performer')),
            'created_at' => $this->created_at,
        ];
    }
}
