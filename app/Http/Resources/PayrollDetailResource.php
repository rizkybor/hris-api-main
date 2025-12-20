<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollDetailResource extends JsonResource
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
            'payroll_id' => $this->payroll_id,
            'employee_id' => $this->employee_id,
            'original_salary' => (float) $this->original_salary,
            'final_salary' => (float) $this->final_salary,
            'attended_days' => $this->attended_days,
            'sick_days' => $this->sick_days,
            'absent_days' => $this->absent_days,
            'notes' => $this->notes,
            'employee' => new EmployeeProfileResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
