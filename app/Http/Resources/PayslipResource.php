<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $basicSalary = (float) $this->original_salary;
        $netSalary = (float) $this->final_salary;
        $totalDeductions = $basicSalary - $netSalary;

        return [
            'id' => $this->id,
            'period' => $this->payroll->salary_month,
            'payment_date' => $this->payroll->payment_date,
            'created_at' => $this->created_at,
            'employee_name' => $this->employee?->user?->name,
            'department' => $this->employee?->jobInformation?->team?->name,
            'basic_salary' => $basicSalary,
            'gross_salary' => $basicSalary,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'notes' => $this->notes,
        ];
    }
}
