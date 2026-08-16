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
        $grossSalary = (float) $this->gross_salary;
        $netSalary = (float) $this->final_salary;
        $attendanceDeduction = $basicSalary - $grossSalary;

        return [
            'id' => $this->id,
            'period' => $this->payroll->salary_month,
            'payment_date' => $this->payroll->payment_date,
            'created_at' => $this->created_at,
            'employee_name' => $this->employee?->user?->name,
            'department' => $this->employee?->jobInformation?->team?->name,
            'basic_salary' => $basicSalary,
            'gross_salary' => $grossSalary,
            'attendance_deduction' => $attendanceDeduction,
            'bpjs_kesehatan_employee' => (float) $this->bpjs_kesehatan_employee,
            'bpjs_jht_employee' => (float) $this->bpjs_jht_employee,
            'bpjs_jp_employee' => (float) $this->bpjs_jp_employee,
            'pph21' => (float) $this->pph21,
            'total_deductions' => (float) $this->total_deduction,
            'net_salary' => $netSalary,
            'notes' => $this->notes,
        ];
    }
}
