<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'name' => $this->name,
            'category' => $this->category,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'purchase_date' => $this->purchase_date,
            'purchase_price' => $this->purchase_price ? (float) $this->purchase_price : null,
            'condition' => $this->condition,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'assignee' => $this->when($this->relationLoaded('assignee') && $this->assignee, fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->user?->name,
                'code' => $this->assignee->code,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
