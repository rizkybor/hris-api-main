<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedCostResource extends JsonResource
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
            'no' => $this->no,
            'financial_items' => $this->financial_items,
            'description' => $this->description,
            'budget' => floatval($this->budget),
            'actual' => floatval($this->actual),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}