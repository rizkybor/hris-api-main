<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'date' => $this->date,
            'check_in' => $this->check_in,
            'check_in_lat' => $this->check_in_lat,
            'check_in_long' => $this->check_in_long,
            'check_out' => $this->check_out,
            'check_out_lat' => $this->check_out_lat,
            'check_out_long' => $this->check_out_long,
            'total_hours' => $this->check_in && $this->check_out
                ? sprintf('%02d:%02d', $this->check_in->diffInHours($this->check_out), $this->check_in->diff($this->check_out)->format('%I'))
                : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'employee' => new EmployeeProfileResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
