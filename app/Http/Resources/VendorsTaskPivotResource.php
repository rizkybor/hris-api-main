<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorsTaskPivotResource extends JsonResource
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
            'maintenance' => (bool) $this->maintenance,
            'contract_value' => $this->contract_value !== null ? (float) $this->contract_value : null,
            'contract_status' => $this->contract_status,
            'contract_start' => $this->contract_start ? $this->contract_start->toDateString() : null,
            'contract_end' => $this->contract_end ? $this->contract_end->toDateString() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relasi
            'vendor' => $this->whenLoaded('vendor', function () {
                return new VendorsResource($this->vendor);
            }),
            'scope_vendor' => $this->whenLoaded('scopeVendor', function () {
                return new VendorsTaskScopeResource($this->scopeVendor);
            }),
            'task_vendor' => $this->whenLoaded('taskVendor', function () {
                return new VendorsTaskListResource($this->taskVendor);
            }),
            'payment_vendor' => $this->whenLoaded('paymentVendor', function () {
                return new VendorsTaskPaymentResource($this->paymentVendor);
            }),
        ];
    }
}
