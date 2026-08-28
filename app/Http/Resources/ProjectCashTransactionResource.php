<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectCashTransactionResource extends JsonResource
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
            'project_id' => $this->project_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => (float) (string) $this->amount,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'notes' => $this->notes,
            // Running balance immediately after this transaction, computed
            // in ProjectCashTransactionController::index() as rows are
            // walked in chronological order -- not a stored column.
            'balance' => $this->when(isset($this->running_balance), fn () => (float) $this->running_balance),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
