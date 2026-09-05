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
            'warranty_expiry_date' => $this->warranty_expiry_date,
            'is_under_warranty' => $this->is_under_warranty,
            'useful_life_months' => $this->useful_life_months,
            'depreciation_method' => $this->depreciation_method,
            'current_book_value' => $this->current_book_value,
            'next_maintenance_due_date' => $this->next_maintenance_due_date,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->when($this->relationLoaded('supplier') && $this->supplier, fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'condition' => $this->condition,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'assignee' => $this->when($this->relationLoaded('assignee') && $this->assignee, fn () => [
                'id' => $this->assignee->id,
                'name' => $this->assignee->user?->name,
                'code' => $this->assignee->code,
            ]),
            'notes' => $this->notes,
            'maintenance_logs' => $this->whenLoaded('maintenanceLogs', function () {
                return AssetMaintenanceLogResource::collection($this->maintenanceLogs);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
