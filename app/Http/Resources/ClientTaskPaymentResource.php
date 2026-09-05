<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientTaskPaymentResource extends JsonResource
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
            'document_name' => $this->document_name,
            'document_path' => $this->document_path,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'payment_date' => $this->payment_date ? $this->payment_date->toDateString() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // // Opsional: jika ingin menampilkan pivot tasks terkait
            // 'client_task' => $this->whenLoaded('clientTask', function () {
            //     return new ClientTaskPivotResource($this->clientTask);
            // }),
        ];
    }
}
