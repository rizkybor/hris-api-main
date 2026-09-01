<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'leave_type' => $this->leave_type,
            'type' => $this->leave_type, // alias for frontend compatibility
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $this->total_days,
            'days' => $this->total_days, // alias for frontend compatibility
            'is_half_day' => (bool) $this->is_half_day,
            'reason' => $this->reason,
            'emergency_contact' => $this->emergency_contact,
            'attachment_original_name' => $this->attachment_original_name,
            'attachment_url' => CloudinaryUrl::auto($this->attachment_path, $this->attachment_mime_type),
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'employee' => new EmployeeProfileResource($this->whenLoaded('employee')),
            'approver' => new EmployeeProfileResource($this->whenLoaded('approver')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
