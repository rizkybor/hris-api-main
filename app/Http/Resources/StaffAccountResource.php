<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->id,
            'user_id' => $this->user_id,
            'code' => $this->code,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'profile_photo' => $this->user?->profile_photo ? asset('storage/'.$this->user->profile_photo) : null,
            'job_title' => $this->jobInformation?->job_title,
            'employment_type' => $this->jobInformation?->employment_type,
            'direct_permissions_count' => $this->user?->getDirectPermissions()->count() ?? 0,
        ];
    }
}
