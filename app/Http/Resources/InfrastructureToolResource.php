<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfrastructureToolResource extends JsonResource
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
            'tech_stack_component' => $this->tech_stack_component,
            'vendor' => $this->vendor,
            'monthly_fee' => floatval($this->monthly_fee),
            'annual_fee' => floatval($this->annual_fee),
            'expired_date' => $this->expired_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}