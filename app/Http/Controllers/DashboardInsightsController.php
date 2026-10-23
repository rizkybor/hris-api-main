<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\MaintenanceContract;
use App\Models\MaintenancePeriod;
use App\Models\Subscription;
use App\Services\KeyMetricsService;
use App\Services\SubscriptionInsightsService;
use App\Services\MaintenanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Data for the Subscriptions and Maintenance dashboard widgets. Each is
 * gated by its own widget permission, like the dashboard grid itself.
 */
class DashboardInsightsController extends Controller implements HasMiddleware
{
    private const TZ = 'Asia/Jakarta';

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['widget-subscriptions']), only: ['subscriptions']),
            new Middleware(PermissionMiddleware::using(['widget-maintenance']), only: ['maintenance']),
            new Middleware(PermissionMiddleware::using(['widget-key-metrics']), only: ['keyMetrics']),
        ];
    }

    /** The Key Metrics widget, tailored to the user's role (see KeyMetricsService). */
    public function keyMetrics(KeyMetricsService $service)
    {
        return ResponseHelper::jsonResponse(true, 'Key Metrics Retrieved Successfully', $service->forUser(Auth::user()), 200);
    }

    /**
     * Active subscriptions, their monthly-equivalent value, what's due in
     * the next 30 days, and the billing expected in each of the next 12
     * months (each subscription's due dates projected forward by its cycle).
     */
    public function subscriptions(SubscriptionInsightsService $insights)
    {
        $active = $insights->active();
        $dueSoon = $insights->dueSoon($active);

        return ResponseHelper::jsonResponse(true, 'Subscription Insights Retrieved Successfully', [
            'active_count' => $active->count(),
            'postponed_count' => Subscription::where('status', 'postponed')->count(),
            'monthly_value' => $insights->monthlyValue($active),
            'due_soon_count' => $dueSoon->count(),
            'due_soon_amount' => round($dueSoon->sum('amount'), 2),
            'due_soon' => $dueSoon->take(4)->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'client' => $s->client?->name,
                'next_due_date' => $s->next_due_date->toDateString(),
                'amount' => (float) $s->amount,
                'overdue' => $insights->isOverdue($s),
            ]),
            'forecast' => $insights->forecast($active),
        ], 200);
    }

    /**
     * This month's quota use per active contract, totals, the development
     * balance, and hours used per month over the last six months.
     */
    public function maintenance(MaintenanceService $service)
    {
        $service->syncAll();
        $current = $service->currentMonth();

        $contracts = MaintenanceContract::with([
            'client:id,name',
            'periods' => fn ($q) => $q->whereDate('period_month', $current.'-01')->withSum('adjustments', 'hours')->withSum('logs', 'hours'),
        ])->where('status', 'active')->get();

        $rows = $contracts->map(function ($contract) use ($service) {
            $period = $contract->periods->first();

            return [
                'id' => $contract->id,
                'name' => $contract->name,
                'client' => $contract->client?->name,
                'period' => $period?->totals(),
                'balance' => $service->balance($contract),
            ];
        });
        $withPeriod = $rows->filter(fn ($r) => $r['period']);

        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now(self::TZ)->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m'));
        $periods = MaintenancePeriod::whereDate('period_month', '>=', $months->first().'-01')
            ->whereHas('contract')
            ->withSum('adjustments', 'hours')->withSum('logs', 'hours')->get()
            ->groupBy(fn ($p) => $p->period_month->format('Y-m'));

        $allContracts = MaintenanceContract::all();

        return ResponseHelper::jsonResponse(true, 'Maintenance Insights Retrieved Successfully', [
            'active_count' => $contracts->count(),
            'used' => round($withPeriod->sum(fn ($r) => $r['period']['used']), 2),
            'total' => round($withPeriod->sum(fn ($r) => $r['period']['total']), 2),
            'over_count' => $withPeriod->filter(fn ($r) => $r['period']['overage'] > 0)->count(),
            'balance' => round($allContracts->sum(fn ($c) => $service->balance($c)), 2),
            'expiring' => round($allContracts->sum(fn ($c) => $service->expiringSoon($c)['hours']), 2),
            'contracts' => $withPeriod->sortByDesc(fn ($r) => $r['period']['used_percent'])->values()->take(6),
            'trend' => $months->map(function ($month) use ($periods) {
                $list = $periods->get($month, collect());

                return [
                    'month' => $month,
                    'used' => round($list->sum(fn ($p) => $p->totals()['used']), 2),
                    'total' => round($list->sum(fn ($p) => $p->totals()['total']), 2),
                ];
            })->values(),
        ], 200);
    }
}
