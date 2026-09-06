<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'profile_photo' => CloudinaryUrl::image($this->profile_photo),
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'employee_profile' => new EmployeeProfileResource($this->whenLoaded('employeeProfile')),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'token' => $this->when(isset($this->token), $this->token),
            'created_at' => $this->created_at,
        ];
    }
}
