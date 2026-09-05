<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientTaskPivotResource extends JsonResource
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
            'client' => $this->whenLoaded('client', function () {
                return new ClientResource($this->client);
            }),
            'scope_client' => $this->whenLoaded('scopeClient', function () {
                return new ClientTaskScopeResource($this->scopeClient);
            }),
            'task_client' => $this->whenLoaded('taskClient', function () {
                return new ClientTaskListResource($this->taskClient);
            }),
            'payment_client' => $this->whenLoaded('paymentClient', function () {
                return new ClientTaskPaymentResource($this->paymentClient);
            }),
        ];
    }
}
