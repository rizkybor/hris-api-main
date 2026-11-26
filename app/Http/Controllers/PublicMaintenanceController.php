<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceBankEntry;
use App\Models\MaintenanceContract;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;

/**
 * The client's view of their maintenance contract, opened from a share
 * link (no login; the token is the only key): this month's quota, the
 * work done, the development balance and features, and past months.
 */
class PublicMaintenanceController extends Controller
{
    public function show(Request $request, string $token, MaintenanceService $service)
    {
        $contract = preg_match('/^[A-Za-z0-9]{32,64}$/', $token)
            ? MaintenanceContract::with(['client', 'project'])->where('share_token', $token)->first()
            : null;

        if (! $contract) {
            return response()->view('maintenance.link-unavailable', [], 404);
        }

        $service->sync($contract);

        $periods = $contract->periods()->withSum('adjustments', 'hours')->withSum('logs', 'hours')
            ->orderByDesc('period_month')->get();
        $requested = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : null;
        $period = ($requested ? $periods->first(fn ($p) => $p->period_month->format('Y-m') === $requested) : null)
            ?? $periods->first(fn ($p) => $p->period_month->format('Y-m') === $service->currentMonth())
            ?? $periods->first();

        $contract->forceFill(['share_last_viewed_at' => now()])->saveQuietly();

        return response()->view('maintenance.public', [
            'contract' => $contract,
            'service' => $service,
            'periods' => $periods,
            'period' => $period,
            'totals' => $period?->totals(),
            'isCurrent' => $period && $period->period_month->format('Y-m') === $service->currentMonth(),
            'logs' => $period ? $period->logs()->where('visible_to_client', true)->orderBy('work_date')->orderBy('id')->get() : collect(),
            'balance' => $service->balance($contract),
            'expiring' => $service->expiringSoon($contract),
            'bankEntries' => self::clientSafe($contract->bankEntries()->with('feature:id,title,visible_to_client')->latest('id')->take(15)->get()),
            'features' => $contract->features()->withSum('logs', 'hours')->where('visible_to_client', true)
                ->where('status', '!=', 'cancelled')->latest('id')->get(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * The note each balance entry may show the client (client_note):
     * manual top-ups/deductions carry an internal reason, and entries of a
     * feature hidden from the client must not reveal its title.
     */
    private static function clientSafe($entries)
    {
        return $entries->each(function (MaintenanceBankEntry $entry) {
            $entry->client_note = match (true) {
                in_array($entry->type, ['adjust_in', 'adjust_out'], true) => null,
                $entry->type === 'expire' => 'Kedaluwarsa',
                $entry->maintenance_feature_id && ! $entry->feature?->visible_to_client => 'Pengembangan fitur',
                default => $entry->note,
            };
        });
    }
}
