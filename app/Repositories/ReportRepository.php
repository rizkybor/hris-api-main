<?php

namespace App\Repositories;

use App\Interfaces\ReportRepositoryInterface;
use App\Models\Attendance;
use App\Models\CompanyFinance;
use App\Models\EmployeeProfile;
use App\Models\FixedCost;
use App\Models\InfrastructureTool;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\ProjectCashTransaction;
use App\Models\ProjectTask;
use App\Models\SdmResource;
use App\Models\StaffTaskAssignee;
use App\Models\Subscription;
use App\Services\StaffPerformanceCalculator;
use Carbon\Carbon;

class ReportRepository implements ReportRepositoryInterface
{
    public function __construct(private StaffPerformanceCalculator $performanceCalculator) {}

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
            'fixed_cost_actual' => (float) $fixedCosts->sum('actual'),
            'infrastructure_monthly_fee' => (float) $infrastructureTools->sum('monthly_fee'),
            'infrastructure_annual_fee' => (float) $infrastructureTools->sum('annual_fee'),
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

    /**
     * PPh 21 recap per employee for payroll runs within the period -- a
     * starting point for the Coretax/Bukti Potong bulk-upload sheet, not a
     * compliance-grade calculation (see PayrollCalculationService).
     */
    public function getPph21Report(?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $payrollIds = Payroll::query()
            ->whereBetween('salary_month', [$startDate, $endDate])
            ->pluck('id');

        $rows = PayrollDetail::query()
            ->whereIn('payroll_id', $payrollIds)
            ->where('pph21', '>', 0)
            ->with(['employee.user', 'employee.jobInformation', 'payroll'])
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->orderBy('payrolls.salary_month', 'desc')
            ->select('payroll_details.*')
            ->get();

        $rows->each(function ($detail) {
            $detail->setAttribute('salary_month', $detail->payroll->salary_month);
        });

        $summary = [
            'total_employees_taxed' => $rows->count(),
            'total_gross_salary' => (float) $rows->sum('gross_salary'),
            'total_pph21' => (float) $rows->sum('pph21'),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * PPN (Pajak Keluaran) recap from issued invoices within the period,
     * keyed by Faktur Pajak date -- a starting point for SPT Masa PPN, not a
     * live Coretax integration.
     */
    public function getPpnReport(?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $rows = Invoice::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('ppn_amount', '>', 0)
            ->orderBy('date', 'desc')
            ->get();

        $summary = [
            'total_invoices' => $rows->count(),
            'total_dpp' => (float) $rows->sum('subtotal'),
            'total_ppn' => (float) $rows->sum('ppn_amount'),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * Project recap filtered by start_date (matching how every other
     * report here filters on one date column, e.g. Payroll on
     * salary_month, PPN on invoice date) -- not an "active during this
     * period" overlap check.
     */
    public function getProjectReport(?string $startDate, ?string $endDate, ?string $status, int $page = 1, int $rowPerPage = 15)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $baseQuery = Project::query()->whereBetween('start_date', [$startDate, $endDate]);

        if ($status) {
            $baseQuery->where('status', $status);
        }

        $summary = [
            'total_projects' => (clone $baseQuery)->count(),
            'active_projects' => (clone $baseQuery)->where('status', 'active')->count(),
            'completed_projects' => (clone $baseQuery)->where('status', 'completed')->count(),
            'total_budget' => (float) (clone $baseQuery)->sum('budget'),
        ];

        $paginated = (clone $baseQuery)
            ->with(['projectLeader.user', 'client'])
            ->withCount([
                'tasks as tasks_total_count',
                'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->orderBy('start_date', 'desc')
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

    /**
     * One row per project cash ledger transaction (debit/credit) within
     * the period, optionally narrowed to a single project -- shows the
     * actual transaction date, unlike the old per-project-aggregate shape
     * this replaced (a whole project doesn't have one meaningful date).
     */
    public function getProjectExpenseReport(?string $startDate, ?string $endDate, ?int $projectId, int $page = 1, int $rowPerPage = 15)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $query = ProjectCashTransaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $totalDebit = (float) (clone $query)->where('type', 'debit')->sum('amount');
        $totalCredit = (float) (clone $query)->where('type', 'credit')->sum('amount');
        $involvedProjectIds = (clone $query)->distinct()->pluck('project_id');
        $totalBudget = (float) Project::whereIn('id', $involvedProjectIds)->sum('budget');

        $summary = [
            'total_projects' => $involvedProjectIds->count(),
            'total_budget' => $totalBudget,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            // Debit = money in, Credit = money out (Indonesian "buku kas"
            // convention -- see ProjectCashTransactionController).
            'total_balance' => $totalBudget + $totalDebit - $totalCredit,
        ];

        $paginated = (clone $query)
            ->with('project:id,name')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($rowPerPage, ['*'], 'page', $page);

        $rows = collect($paginated->items())->map(fn ($transaction) => [
            'id' => $transaction->id,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'project_id' => $transaction->project_id,
            'project_name' => $transaction->project?->name,
            'description' => $transaction->description,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
        ]);

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $rows,
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

    /**
     * PPh 23 recap from Payment Receipts where the client withheld it
     * (pph23_amount set) within the period, keyed by receipt date -- a
     * starting point for Bukti Potong/Coretax, same spirit as getPpnReport.
     */
    public function getPph23Report(?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfYear()->toDateString();
        $endDate = $endDate ?: now()->endOfYear()->toDateString();

        $rows = PaymentReceipt::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('pph23_amount', '>', 0)
            ->with('invoice:id,invoice_number,client_name')
            ->orderBy('date', 'desc')
            ->get();

        $summary = [
            'total_receipts' => $rows->count(),
            'total_gross' => (float) $rows->sum(fn ($r) => (float) $r->amount + (float) $r->pph23_amount),
            'total_pph23' => (float) $rows->sum('pph23_amount'),
            'total_net_received' => (float) $rows->sum('amount'),
        ];

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * Recurring-billing snapshot -- unlike every other report here, this
     * isn't scoped to a date range: MRR/ARR and status counts are a "right
     * now" snapshot of the subscriptions table, not a recap of what
     * happened between two dates. Only active subscriptions count toward
     * MRR/ARR; postponed/cancelled ones are paused or stopped billing.
     */
    public function getSubscriptionReport(?string $status = null)
    {
        $query = Subscription::query()->with(['client:id,name', 'project:id,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $rows = $query->orderBy('next_due_date')->get();

        $monthlyValue = fn (Subscription $s) => $s->billing_cycle === 'yearly'
            ? (float) $s->amount / 12
            : (float) $s->amount;

        $active = $rows->where('status', 'active');
        $mrr = (float) $active->sum($monthlyValue);

        $statusCounts = $rows->groupBy('status');
        $statusBreakdown = collect(['active', 'postponed', 'cancelled'])->map(fn ($key) => [
            'status' => $key,
            'count' => $statusCounts->get($key, collect())->count(),
            'mrr' => $key === 'active' ? $mrr : (float) $statusCounts->get($key, collect())->sum($monthlyValue),
        ])->values();

        $upcomingRenewals = $active
            ->filter(fn (Subscription $s) => $s->next_due_date && $s->next_due_date->lte(now()->addDays(30)))
            ->sortBy('next_due_date')
            ->values();

        $summary = [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'active_count' => $active->count(),
            'postponed_count' => $rows->where('status', 'postponed')->count(),
            'cancelled_count' => $rows->where('status', 'cancelled')->count(),
            'total_count' => $rows->count(),
            'upcoming_renewals_count' => $upcomingRenewals->count(),
        ];

        return [
            'summary' => $summary,
            'status_breakdown' => $statusBreakdown,
            'upcoming_renewals' => $upcomingRenewals,
            'rows' => $rows,
        ];
    }

    public function getStaffRaportList(?string $search, ?string $employmentType, ?string $startDate, ?string $endDate, int $page = 1, int $rowPerPage = 15)
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->endOfMonth()->toDateString();

        $query = EmployeeProfile::query()->with(['user', 'jobInformation.team']);

        if ($search) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($employmentType) {
            $query->whereHas('jobInformation', fn ($q) => $q->where('employment_type', $employmentType));
        }

        $employees = $query->orderBy('created_at', 'desc')->get();
        $total = $employees->count();

        // The score itself is computed per-employee below, so pagination
        // slices the already-loaded collection rather than the query --
        // there's no way to paginate at the SQL level when the sort/filter
        // criteria (the computed score) doesn't exist as a column.
        $paged = $employees->slice(($page - 1) * $rowPerPage, $rowPerPage)->values();

        $rows = $paged->map(function (EmployeeProfile $employee) use ($startDate, $endDate) {
            $performance = $this->performanceCalculator->calculate($employee->id, $startDate, $endDate);

            return [
                'employee_id' => $employee->id,
                'name' => $employee->user?->name,
                'code' => $employee->code,
                'job_title' => $employee->jobInformation?->job_title,
                'employment_type' => $employee->jobInformation?->employment_type,
                'team' => $employee->jobInformation?->team?->name,
                'attendance_rate' => $performance['attendance_rate'],
                'task_completion_rate' => $performance['task_completion_rate'],
                'overall_score' => $performance['overall_score'],
                'stars' => $performance['stars'],
            ];
        });

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'rows' => $rows,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $rowPerPage)),
                'per_page' => $rowPerPage,
                'total' => $total,
                'from' => $total === 0 ? null : ($page - 1) * $rowPerPage + 1,
                'to' => $total === 0 ? null : min($page * $rowPerPage, $total),
            ],
        ];
    }

    public function getStaffRaportDetail(int $employeeId, ?string $startDate, ?string $endDate)
    {
        $startDate = $startDate ?: now()->startOfMonth()->toDateString();
        $endDate = $endDate ?: now()->endOfMonth()->toDateString();

        $employee = EmployeeProfile::with(['user', 'jobInformation.team'])->findOrFail($employeeId);
        $performance = $this->performanceCalculator->calculate($employeeId, $startDate, $endDate);

        $completedProjectTasks = ProjectTask::where('assignee_id', $employeeId)
            ->where('status', 'done')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->with('project:id,name')
            ->orderByDesc('due_date')
            ->get()
            ->map(fn ($t) => [
                'source' => 'project_task',
                'title' => $t->name,
                'project_name' => $t->project?->name,
                'due_date' => $t->due_date,
            ]);

        $completedStaffTasks = StaffTaskAssignee::where('employee_id', $employeeId)
            ->where('status', 'done')
            ->whereHas('task', fn ($q) => $q->whereBetween('due_date', [$startDate, $endDate]))
            ->with('task:id,title,due_date')
            ->get()
            ->map(fn ($a) => [
                'source' => 'staff_task',
                'title' => $a->task?->title,
                'project_name' => null,
                'due_date' => $a->task?->due_date,
            ]);

        $completedTasks = $completedProjectTasks->concat($completedStaffTasks)
            ->sortByDesc('due_date')
            ->values();

        // Latest submitted review regardless of this raport's own date
        // range -- Performance Review runs on its own (quarterly/annual)
        // cadence, not the week/month/all window picked for the raport.
        $latestReview = PerformanceReview::where('employee_id', $employeeId)
            ->with('reviewer:id,name')
            ->orderByDesc('period_end')
            ->first();

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
                'code' => $employee->code,
                'profile_photo' => $employee->user?->profile_photo,
                'job_title' => $employee->jobInformation?->job_title,
                'employment_type' => $employee->jobInformation?->employment_type,
                'team' => $employee->jobInformation?->team?->name,
                'start_date' => $employee->jobInformation?->start_date,
            ],
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'attendance' => $performance['attendance'],
            'attendance_rate' => $performance['attendance_rate'],
            'tasks' => $performance['tasks'],
            'task_completion_rate' => $performance['task_completion_rate'],
            'completed_tasks' => $completedTasks,
            'overall_score' => $performance['overall_score'],
            'stars' => $performance['stars'],
            'performance_review' => $latestReview ? [
                'period' => $latestReview->period,
                'period_start' => $latestReview->period_start,
                'period_end' => $latestReview->period_end,
                'overall_rating' => (float) $latestReview->overall_rating,
                'category_scores' => $latestReview->category_scores,
                'strengths' => $latestReview->strengths,
                'areas_for_improvement' => $latestReview->areas_for_improvement,
                'goals_next_period' => $latestReview->goals_next_period,
                'status' => $latestReview->status,
                'reviewer_name' => $latestReview->reviewer?->name,
            ] : null,
        ];
    }
}
