<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'rating' => $this->rating,
            'notes' => $this->notes,
            'evaluated_at' => $this->evaluated_at,
            'evaluated_by' => new UserResource($this->whenLoaded('evaluator')),
            'created_at' => $this->created_at,
        ];
    }
}
