<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProfileResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'code' => $this->code,
            'identity_number' => $this->identity_number,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'hobby' => $this->hobby,
            'place_of_birth' => $this->place_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'preferred_language' => $this->preferred_language,
            'additional_notes' => $this->additional_notes,

            'job_information' => new JobInformationResource($this->whenLoaded('jobInformation')),
            'bank_information' => new BankInformationResource($this->whenLoaded('bankInformation')),
            'emergency_contacts' => EmergencyContactResource::collection($this->whenLoaded('emergencyContacts')),
            'team' => new TeamResource($this->whenLoaded('team')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
