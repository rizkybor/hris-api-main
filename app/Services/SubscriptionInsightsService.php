<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Figures behind the Subscriptions page recap and the dashboard widget:
 * monthly-equivalent value (yearly billed counts as 1/12 per month), what's
 * due soon or overdue, value by service type and client, and the billing
 * expected in each of the next 12 months.
 */
class SubscriptionInsightsService
{
    private const TZ = 'Asia/Jakarta';

    private Carbon $today;

    public function __construct()
    {
        $this->today = Carbon::now(self::TZ)->startOfDay();
    }

    public function active(): Collection
    {
        return Subscription::with(['services', 'client:id,name'])->where('status', 'active')->get();
    }

    private function monthly(Subscription $s, float $amount): float
    {
        return $s->billing_cycle === 'yearly' ? $amount / 12 : $amount;
    }

    /** Each active subscription's due dates rolled forward by its cycle. */
    public function forecast(Collection $active, int $months = 12): array
    {
        $first = $this->today->copy()->startOfMonth();
        $last = $first->copy()->addMonthsNoOverflow($months - 1)->endOfMonth();

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $buckets[$first->copy()->addMonthsNoOverflow($i)->format('Y-m')] = ['amount' => 0.0, 'count' => 0];
        }

        foreach ($active as $s) {
            if (! $s->next_due_date) {
                continue;
            }
            $step = $s->billing_cycle === 'yearly' ? 12 : 1;
            $due = Carbon::parse($s->next_due_date->toDateString(), self::TZ);
            // Overdue bills still belong to the current month.
            if ($due->lt($first)) {
                $due = $first->copy();
            }
            for ($guard = 0; $due->lte($last) && $guard < 24; $guard++) {
                $key = $due->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['amount'] += $s->amount;
                    $buckets[$key]['count']++;
                }
                $due = $due->copy()->addMonthsNoOverflow($step);
            }
        }

        return collect($buckets)->map(fn ($v, $month) => ['month' => $month, 'amount' => round($v['amount'], 2), 'count' => $v['count']])->values()->all();
    }

    /** Active subscriptions due within $days days (overdue included), soonest first. */
    public function dueSoon(Collection $active, int $days = 30): Collection
    {
        return $active
            ->filter(fn ($s) => $s->next_due_date && $s->next_due_date->lte($this->today->copy()->addDays($days)))
            ->sortBy('next_due_date')
            ->values();
    }

    public function isOverdue(Subscription $s): bool
    {
        return $s->next_due_date && $s->next_due_date->lt($this->today);
    }

    public function monthlyValue(Collection $active): float
    {
        return round($active->sum(fn ($s) => $this->monthly($s, $s->amount)), 2);
    }

    /** Everything the Subscriptions page recap shows. */
    public function summary(): array
    {
        $active = $this->active();
        $counts = Subscription::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $dueSoon = $this->dueSoon($active);
        $overdue = $dueSoon->filter(fn ($s) => $this->isOverdue($s));
        $monthly = $this->monthlyValue($active);

        $byType = [];
        foreach ($active as $s) {
            foreach ($s->services as $service) {
                $type = $service->service_type ?: 'other';
                $byType[$type] ??= ['type' => $type, 'count' => 0, 'monthly_value' => 0.0];
                $byType[$type]['count']++;
                $byType[$type]['monthly_value'] += $this->monthly($s, (float) $service->amount);
            }
        }

        $byClient = $active->groupBy(fn ($s) => $s->client?->name ?? 'No client')
            ->map(fn ($list, $name) => [
                'client' => $name,
                'count' => $list->count(),
                'monthly_value' => round($list->sum(fn ($s) => $this->monthly($s, $s->amount)), 2),
            ])
            ->sortByDesc('monthly_value')
            ->values();

        return [
            'counts' => [
                'active' => (int) ($counts['active'] ?? 0),
                'postponed' => (int) ($counts['postponed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ],
            'monthly_value' => $monthly,
            'annual_value' => round($monthly * 12, 2),
            'due_soon' => ['count' => $dueSoon->count(), 'amount' => round($dueSoon->sum('amount'), 2)],
            'overdue' => ['count' => $overdue->count(), 'amount' => round($overdue->sum('amount'), 2)],
            'by_service_type' => collect($byType)->map(fn ($t) => [...$t, 'monthly_value' => round($t['monthly_value'], 2)])
                ->sortByDesc('monthly_value')->values()->all(),
            'top_clients' => $byClient->take(5)->all(),
            'client_count' => $byClient->count(),
            'forecast' => $this->forecast($active),
        ];
    }
}
