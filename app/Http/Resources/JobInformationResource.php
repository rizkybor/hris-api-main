<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobInformationResource extends JsonResource
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
            'job_title' => $this->job_title,
            'team' => new TeamResource($this->whenLoaded('team')),
            'years_experience' => $this->years_experience,
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->monthly_salary,
            'skill_level' => $this->skill_level,
        ];
    }
}
