<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
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
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'employee' => new EmployeeProfileResource($this->whenLoaded('employee')),
        ];
    }
}
