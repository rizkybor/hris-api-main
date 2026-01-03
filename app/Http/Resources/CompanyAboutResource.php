<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyAboutResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'vision' => $this->vision,
            'mission' => $this->mission,
            'branches' => $this->branches,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'established_date' => $this->established_date ? $this->established_date->toDateString() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
