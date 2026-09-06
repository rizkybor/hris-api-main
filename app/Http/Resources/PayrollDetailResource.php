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
            'gross_salary' => (float) $this->gross_salary,
            'final_salary' => (float) $this->final_salary,
            'attended_days' => $this->attended_days,
            'sick_days' => $this->sick_days,
            'absent_days' => $this->absent_days,
            'months_of_service' => $this->months_of_service,
            'bpjs_kesehatan_employee' => (float) $this->bpjs_kesehatan_employee,
            'bpjs_jht_employee' => (float) $this->bpjs_jht_employee,
            'bpjs_jp_employee' => (float) $this->bpjs_jp_employee,
            'bpjs_kesehatan_company' => (float) $this->bpjs_kesehatan_company,
            'bpjs_jht_company' => (float) $this->bpjs_jht_company,
            'bpjs_jp_company' => (float) $this->bpjs_jp_company,
            'bpjs_jkk_company' => (float) $this->bpjs_jkk_company,
            'bpjs_jkm_company' => (float) $this->bpjs_jkm_company,
            'pph21' => (float) $this->pph21,
            'total_deduction' => (float) $this->total_deduction,
            'notes' => $this->notes,
            'payment_mode' => $this->payment_mode,
            'source_project_id' => $this->source_project_id,
            'project_percentage' => $this->project_percentage !== null ? (float) $this->project_percentage : null,
            'source_project' => $this->whenLoaded('sourceProject', fn () => $this->sourceProject ? [
                'id' => $this->sourceProject->id,
                'name' => $this->sourceProject->name,
                'budget' => (float) (string) $this->sourceProject->budget,
            ] : null),
            'employee' => new EmployeeProfileResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
