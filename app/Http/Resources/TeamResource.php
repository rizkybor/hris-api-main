<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
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
            'expected_size' => $this->expected_size,
            'description' => $this->description,
            'icon' => $this->icon ? asset('storage/'.$this->icon) : null,
            'department' => $this->department,
            'status' => $this->status,
            'leader' => new UserResource($this->whenLoaded('leader')),
            'responsibilities' => $this->responsibilities,
            'members_count' => $this->members_count ?? null,
            'members' => TeamMemberResource::collection($this->whenLoaded('members')),
            'projects_count' => $this->projects_count ?? null,
            'created_at' => $this->created_at,
        ];
    }
}
