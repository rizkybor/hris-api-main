<?php

namespace App\Repositories;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Attendance;
use App\Models\CompanyFinance;
use App\Models\EmployeeProfile;
use App\Models\FixedCost;
use App\Models\InfrastructureTool;
use App\Models\Payroll;
use App\Models\SdmResource;
use Carbon\Carbon;

class ReportRepository implements ReportRepositoryInterface
{
    public function getAttendanceReport(?string $startDate, ?string $endDate, ?int $employeeId, ?string $status)
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->endOfMonth()->toDateString();

        $query = Attendance::query()
            ->with(['employee.user', 'employee.jobInformation.team'])
            ->whereBetween('date', [$startDate, $endDate]);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('date', 'desc')->get();

        $summary = [
            'total_records' => $rows->count(),
            'present' => $rows->where('status', 'present')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'sick_leave' => $rows->where('status', 'sick_leave')->count(),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function getPayrollReport(?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $payrolls = Payroll::query()
            ->with(['payrollDetails.employee.user', 'payrollDetails.employee.jobInformation.team'])
            ->whereBetween('salary_month', [$startDate, $endDate])
            ->orderBy('salary_month', 'desc')
            ->get();

        $details = $payrolls->flatMap(function ($payroll) {
            return $payroll->payrollDetails->map(function ($detail) use ($payroll) {
                $detail->setAttribute('salary_month', $payroll->salary_month);
                $detail->setAttribute('payment_status', $payroll->status);

                return $detail;
            });
        });

        $summary = [
            'total_payroll_runs' => $payrolls->count(),
            'total_employees_paid' => $details->count(),
            'total_original_salary' => (float) $details->sum('original_salary'),
            'total_final_salary' => (float) $details->sum('final_salary'),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $details->values(),
        ];
    }

    public function getEmployeeReport(?int $teamId, ?string $status)
    {
        $query = EmployeeProfile::query()
            ->with(['user', 'jobInformation.team']);

        if ($teamId) {
            $query->whereHas('jobInformation', function ($q) use ($teamId) {
                $q->where('team_id', $teamId);
            });
        }

        if ($status) {
            $query->whereHas('jobInformation', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $rows = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_employees' => $rows->count(),
            'active' => $rows->filter(fn ($e) => $e->jobInformation?->status === 'active')->count(),
            'inactive' => $rows->filter(fn ($e) => $e->jobInformation?->status !== 'active')->count(),
            'by_gender' => $rows->groupBy('gender')->map->count(),
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function getFinanceReport(?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $range = [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];

        $fixedCosts = FixedCost::whereBetween('created_at', $range)->get();
        $infrastructureTools = InfrastructureTool::whereBetween('created_at', $range)->get();
        $sdmResources = SdmResource::whereBetween('created_at', $range)->get();
        $latestBalance = CompanyFinance::orderBy('created_at', 'desc')->first();

        $summary = [
            'company_balance' => (float) ($latestBalance->saldo_company ?? 0),
            'fixed_cost_budget' => (float) $fixedCosts->sum('budget'),
            'fixed_cost_actual' => (float) $fixedCosts->sum('actual'),
            'infrastructure_monthly_fee' => (float) $infrastructureTools->sum('monthly_fee'),
            'infrastructure_annual_fee' => (float) $infrastructureTools->sum('annual_fee'),
            'sdm_budget' => (float) $sdmResources->sum('budget'),
            'sdm_actual' => (float) $sdmResources->sum('actual'),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => [
                'fixed_costs' => $fixedCosts,
                'infrastructure_tools' => $infrastructureTools,
                'sdm_resources' => $sdmResources,
            ],
        ];
    }
}
