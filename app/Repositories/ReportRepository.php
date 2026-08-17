<?php

namespace App\Repositories;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Attendance;
use App\Models\CompanyFinance;
use App\Models\EmployeeProfile;
use App\Models\FixedCost;
use App\Models\InfrastructureTool;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\SdmResource;
use Carbon\Carbon;

class ReportRepository implements ReportRepositoryInterface
{
    public function getAttendanceReport(?string $startDate, ?string $endDate, ?int $employeeId, ?string $status, int $page = 1, int $rowPerPage = 15)
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->endOfMonth()->toDateString();

        $baseQuery = Attendance::query()->whereBetween('date', [$startDate, $endDate]);

        if ($employeeId) {
            $baseQuery->where('employee_id', $employeeId);
        }

        if ($status) {
            $baseQuery->where('status', $status);
        }

        $summary = [
            'total_records' => (clone $baseQuery)->count(),
            'present' => (clone $baseQuery)->where('status', 'present')->count(),
            'late' => (clone $baseQuery)->where('status', 'late')->count(),
            'absent' => (clone $baseQuery)->where('status', 'absent')->count(),
            'sick_leave' => (clone $baseQuery)->where('status', 'sick_leave')->count(),
        ];

        $paginated = (clone $baseQuery)
            ->with(['employee.user', 'employee.jobInformation.team'])
            ->orderBy('date', 'desc')
            ->paginate($rowPerPage, ['*'], 'page', $page);

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }

    public function getPayrollReport(?string $startDate, ?string $endDate, int $page = 1, int $rowPerPage = 15)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $payrollIds = Payroll::query()
            ->whereBetween('salary_month', [$startDate, $endDate])
            ->pluck('id');

        $summary = [
            'total_payroll_runs' => $payrollIds->count(),
            'total_employees_paid' => PayrollDetail::query()->whereIn('payroll_id', $payrollIds)->count(),
            'total_original_salary' => (float) PayrollDetail::query()->whereIn('payroll_id', $payrollIds)->sum('original_salary'),
            'total_final_salary' => (float) PayrollDetail::query()->whereIn('payroll_id', $payrollIds)->sum('final_salary'),
        ];

        $paginated = PayrollDetail::query()
            ->whereIn('payroll_id', $payrollIds)
            ->with(['employee.user', 'employee.jobInformation.team', 'payroll'])
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->orderBy('payrolls.salary_month', 'desc')
            ->select('payroll_details.*')
            ->paginate($rowPerPage, ['*'], 'page', $page);

        $paginated->getCollection()->transform(function ($detail) {
            $detail->setAttribute('salary_month', $detail->payroll->salary_month);
            $detail->setAttribute('payment_status', $detail->payroll->status);

            return $detail;
        });

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
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
