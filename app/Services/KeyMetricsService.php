<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The Key Metrics dashboard widget, tailored per role: a business view for
 * Manager / Operational Director, a cash view for Finance and a people view
 * for HR. Each metric is also dropped when the user can't open the module
 * it summarises, so a role with trimmed permissions never sees numbers it
 * isn't otherwise allowed to see.
 *
 * Every metric: key, label, value, format (rupiah|number|percent), sub
 * (one line of context), optional delta {value, direction, label} and the
 * route it links to.
 */
class KeyMetricsService
{
    private const TZ = 'Asia/Jakarta';

    // First matching role wins.
    private const PROFILE_BY_ROLE = [
        'manager' => 'business',
        'operational_director' => 'business',
        'finance' => 'finance',
        'hr' => 'people',
    ];

    private const PROFILES = [
        'business' => ['revenue', 'receivables', 'projects', 'recurring', 'present_today'],
        'finance' => ['cash_balance', 'cash_in', 'cash_out', 'receivables', 'payroll'],
        'people' => ['employees', 'present_today', 'attendance_rate', 'pending_leave', 'on_leave'],
    ];

    // Permission needed to see each metric (any of them).
    private const PERMISSIONS = [
        'revenue' => ['payment-receipt-menu', 'payment-receipt-list'],
        'receivables' => ['invoice-menu', 'invoice-list'],
        'projects' => ['project-menu', 'project-list'],
        'recurring' => ['subscription-menu', 'subscription-list'],
        'present_today' => ['attendance-list', 'employee-menu'],
        'cash_balance' => ['company-cash-book-list'],
        'cash_in' => ['company-cash-book-list'],
        'cash_out' => ['company-cash-book-list'],
        'payroll' => ['payroll-list', 'payroll-statistics'],
        'employees' => ['employee-menu', 'employee-list'],
        'attendance_rate' => ['attendance-list'],
        'pending_leave' => ['leave-request-list', 'leave-request-approve'],
        'on_leave' => ['leave-request-list'],
    ];

    private Carbon $today;

    public function __construct()
    {
        $this->today = Carbon::now(self::TZ)->startOfDay();
    }

    public function profileFor(User $user): string
    {
        foreach (self::PROFILE_BY_ROLE as $role => $profile) {
            if ($user->hasRole($role)) {
                return $profile;
            }
        }

        return 'business';
    }

    public function forUser(User $user): array
    {
        $profile = $this->profileFor($user);
        $metrics = [];

        foreach (self::PROFILES[$profile] as $key) {
            if (! collect(self::PERMISSIONS[$key])->contains(fn ($p) => $user->can($p))) {
                continue;
            }
            $metrics[] = ['key' => $key, ...$this->{'metric'.str_replace('_', '', ucwords($key, '_'))}()];
        }

        return [
            'profile' => $profile,
            'period' => $this->today->copy()->locale('id')->translatedFormat('F Y'),
            'metrics' => $metrics,
        ];
    }

    // ------------------------------------------------------------ business

    private function metricRevenue(): array
    {
        $sum = fn (Carbon $from, Carbon $to) => (float) DB::table('payment_receipts')
            ->where('status', 'active')->whereNull('deleted_at')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $start = $this->today->copy()->startOfMonth();
        $current = $sum($start, $this->today);
        // Same stretch of last month (1st to today's date) for a fair compare.
        $lastStart = $start->copy()->subMonthNoOverflow();
        $previous = $sum($lastStart, $lastStart->copy()->addDays($this->today->day - 1)->min($lastStart->copy()->endOfMonth()));
        $count = DB::table('payment_receipts')->where('status', 'active')->whereNull('deleted_at')
            ->whereBetween('date', [$start->toDateString(), $this->today->toDateString()])->count();

        return [
            'label' => 'Revenue this month',
            'value' => $current,
            'format' => 'rupiah',
            'sub' => $count.' payment'.($count === 1 ? '' : 's').' received',
            'delta' => $this->percentDelta($current, $previous, 'vs same period last month'),
            'route' => 'admin.payment-receipts.dashboard',
        ];
    }

    private function metricReceivables(): array
    {
        $unpaid = DB::table('invoices')->where('status', 'unpaid')->whereNull('deleted_at');
        $total = (float) (clone $unpaid)->sum('total');
        $count = (clone $unpaid)->count();
        $oldest = (clone $unpaid)->min('date');
        $older30 = (clone $unpaid)->whereDate('date', '<', $this->today->copy()->subDays(30)->toDateString())->count();

        return [
            'label' => 'Unpaid invoices',
            'value' => $total,
            'format' => 'rupiah',
            'sub' => $count ? $count.' invoice'.($count === 1 ? '' : 's').($older30 ? " · {$older30} older than 30 days" : '') : 'All invoices paid',
            'tone' => $older30 ? 'warning' : null,
            'oldest' => $oldest,
            'route' => 'admin.invoices.dashboard',
        ];
    }

    private function metricProjects(): array
    {
        $active = DB::table('projects')->where('status', 'active')->whereNull('deleted_at');
        $count = (clone $active)->count();
        $overdue = (clone $active)->whereNotNull('end_date')->whereDate('end_date', '<', $this->today->toDateString())->count();
        $new = (clone $active)->where('created_at', '>=', $this->today->copy()->startOfMonth()->utc())->count();

        return [
            'label' => 'Active projects',
            'value' => $count,
            'format' => 'number',
            'sub' => $overdue ? "{$overdue} past deadline" : 'None past deadline',
            'tone' => $overdue ? 'danger' : null,
            'delta' => $new ? ['value' => $new, 'direction' => 'up', 'label' => 'new this month'] : null,
            'route' => 'admin.projects',
        ];
    }

    private function metricRecurring(): array
    {
        $active = Subscription::with('services')->where('status', 'active')->get();
        $monthly = $active->sum(fn ($s) => $s->billing_cycle === 'yearly' ? $s->amount / 12 : $s->amount);
        $dueSoon = $active->filter(fn ($s) => $s->next_due_date && $s->next_due_date->lte($this->today->copy()->addDays(30)))->count();

        return [
            'label' => 'Recurring revenue / month',
            'value' => round($monthly, 2),
            'format' => 'rupiah',
            'sub' => $active->count().' active subscription'.($active->count() === 1 ? '' : 's').($dueSoon ? " · {$dueSoon} due in 30 days" : ''),
            'route' => 'admin.subscriptions.dashboard',
        ];
    }

    private function metricPresentToday(): array
    {
        $employees = $this->activeEmployees();
        $today = DB::table('attendances')->whereDate('date', $this->today->toDateString())->whereIn('status', ['present', 'late']);
        $present = (clone $today)->distinct('employee_id')->count('employee_id');
        $late = (clone $today)->where('status', 'late')->count();

        return [
            'label' => 'Present today',
            'value' => $present,
            'format' => 'number',
            'suffix' => ' / '.$employees,
            'sub' => $late ? "{$late} arrived late" : ($present ? 'No late arrivals' : 'No check-ins yet'),
            'progress' => $employees ? round($present / $employees * 100) : 0,
            'route' => 'admin.attendances',
        ];
    }

    // ------------------------------------------------------------ finance

    private function cashBetween(string $type, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('company_cash_transactions')->where('type', $type)->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
    }

    private function metricCashBalance(): array
    {
        $opening = (float) (DB::table('company_cash_balances')->orderByDesc('id')->value('opening_balance') ?? 0);
        $in = (float) DB::table('company_cash_transactions')->where('type', 'debit')->whereNull('deleted_at')->sum('amount');
        $out = (float) DB::table('company_cash_transactions')->where('type', 'credit')->whereNull('deleted_at')->sum('amount');
        $start = $this->today->copy()->startOfMonth();
        $net = $this->cashBetween('debit', $start, $this->today) - $this->cashBetween('credit', $start, $this->today);

        return [
            'label' => 'Cash balance',
            'value' => $opening + $in - $out,
            'format' => 'rupiah',
            'sub' => 'Company cash book, opening balance included',
            'delta' => $net == 0 ? null : ['value' => abs($net), 'format' => 'rupiah', 'direction' => $net > 0 ? 'up' : 'down', 'label' => 'net this month'],
            'route' => 'admin.company-cash-book.dashboard',
        ];
    }

    private function cashFlowMetric(string $type, string $label): array
    {
        $start = $this->today->copy()->startOfMonth();
        $current = $this->cashBetween($type, $start, $this->today);
        $lastStart = $start->copy()->subMonthNoOverflow();
        $previous = $this->cashBetween($type, $lastStart, $lastStart->copy()->addDays($this->today->day - 1)->min($lastStart->copy()->endOfMonth()));
        $count = DB::table('company_cash_transactions')->where('type', $type)->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$start->toDateString(), $this->today->toDateString()])->count();

        return [
            'label' => $label,
            'value' => $current,
            'format' => 'rupiah',
            'sub' => $count.' transaction'.($count === 1 ? '' : 's'),
            'delta' => $this->percentDelta($current, $previous, 'vs same period last month', $type === 'credit'),
            'route' => 'admin.company-cash-book.dashboard',
        ];
    }

    private function metricCashIn(): array
    {
        return $this->cashFlowMetric('debit', 'Cash in this month');
    }

    private function metricCashOut(): array
    {
        return $this->cashFlowMetric('credit', 'Cash out this month');
    }

    private function metricPayroll(): array
    {
        $payroll = DB::table('payrolls')->whereNull('deleted_at')->where('type', 'monthly')
            ->whereDate('salary_month', $this->today->copy()->startOfMonth()->toDateString())->first();

        if (! $payroll) {
            return [
                'label' => 'Payroll this month',
                'value' => null,
                'format' => 'rupiah',
                'display' => 'Not run yet',
                'sub' => 'No payroll for '.$this->today->copy()->locale('id')->translatedFormat('F'),
                'tone' => 'warning',
                'route' => 'admin.payroll.dashboard',
            ];
        }

        $details = DB::table('payroll_details')->where('payroll_id', $payroll->id)->whereNull('deleted_at');

        return [
            'label' => 'Payroll this month',
            'value' => (float) (clone $details)->sum('final_salary'),
            'format' => 'rupiah',
            'sub' => (clone $details)->count().' employees · '.ucfirst($payroll->status),
            'tone' => $payroll->status === 'paid' ? null : 'warning',
            'route' => 'admin.payroll.dashboard',
        ];
    }

    // ------------------------------------------------------------ people

    private function activeEmployees(): int
    {
        return DB::table('employee_profiles')->whereNull('deleted_at')->count();
    }

    private function metricEmployees(): array
    {
        $new = DB::table('employee_profiles')->whereNull('deleted_at')
            ->where('created_at', '>=', $this->today->copy()->startOfMonth()->utc())->count();
        $teams = DB::table('teams')->count();

        return [
            'label' => 'Employees',
            'value' => $this->activeEmployees(),
            'format' => 'number',
            'sub' => $teams.' team'.($teams === 1 ? '' : 's'),
            'delta' => $new ? ['value' => $new, 'direction' => 'up', 'label' => 'joined this month'] : null,
            'route' => 'admin.employees',
        ];
    }

    /** Check-ins (present/late) over working days (Mon-Fri) so far this month. */
    private function metricAttendanceRate(): array
    {
        $rate = function (Carbon $from, Carbon $to) {
            $workdays = 0;
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $workdays += $d->isWeekday() ? 1 : 0;
            }
            $expected = $this->activeEmployees() * $workdays;
            $actual = DB::table('attendances')->whereIn('status', ['present', 'late'])
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->count();

            return $expected > 0 ? round(min(100, $actual / $expected * 100), 1) : null;
        };

        $start = $this->today->copy()->startOfMonth();
        $current = $rate($start, $this->today);
        $lastStart = $start->copy()->subMonthNoOverflow();
        $previous = $rate($lastStart, $lastStart->copy()->endOfMonth());
        $diff = $current !== null && $previous !== null ? round($current - $previous, 1) : null;

        return [
            'label' => 'Attendance rate',
            'value' => $current ?? 0,
            'format' => 'percent',
            'sub' => 'Working days this month',
            'progress' => $current ?? 0,
            'delta' => $diff ? ['value' => abs($diff), 'format' => 'points', 'direction' => $diff > 0 ? 'up' : 'down', 'good' => $diff > 0, 'label' => 'vs last month'] : null,
            'route' => 'admin.attendances',
        ];
    }

    private function metricPendingLeave(): array
    {
        $pending = DB::table('leave_requests')->where('status', 'pending')->whereNull('deleted_at');
        $count = (clone $pending)->count();
        $soon = (clone $pending)->whereDate('start_date', '<=', $this->today->copy()->addDays(7)->toDateString())->count();

        return [
            'label' => 'Pending leave requests',
            'value' => $count,
            'format' => 'number',
            'sub' => $count ? ($soon ? "{$soon} start within 7 days" : 'None starting this week') : 'Nothing to review',
            'tone' => $soon ? 'warning' : null,
            'route' => 'admin.leave-requests.dashboard',
        ];
    }

    private function metricOnLeave(): array
    {
        $today = $this->today->toDateString();
        $count = DB::table('leave_requests')->where('status', 'approved')->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->distinct('employee_id')->count('employee_id');
        $upcoming = DB::table('leave_requests')->where('status', 'approved')->whereNull('deleted_at')
            ->whereDate('start_date', '>', $today)->whereDate('start_date', '<=', $this->today->copy()->addDays(7)->toDateString())->count();

        return [
            'label' => 'On leave today',
            'value' => $count,
            'format' => 'number',
            'sub' => $upcoming ? "{$upcoming} more in the next 7 days" : 'None scheduled this week',
            'route' => 'admin.leave-requests.dashboard',
        ];
    }

    // ------------------------------------------------------------ helpers

    /** % change; for spending ($lowerIsBetter) a rise is flagged as bad. */
    private function percentDelta(float $current, float $previous, string $label, bool $lowerIsBetter = false): ?array
    {
        if ($previous <= 0) {
            return null;
        }
        $change = round(($current - $previous) / $previous * 100, 1);
        if ($change == 0) {
            return null;
        }
        $up = $change > 0;

        return [
            'value' => abs($change),
            'format' => 'percent',
            'direction' => $up ? 'up' : 'down',
            'good' => $lowerIsBetter ? ! $up : $up,
            'label' => $label,
        ];
    }
}
