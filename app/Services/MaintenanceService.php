<?php

namespace App\Services;

use App\Models\MaintenanceBankEntry;
use App\Models\MaintenanceContract;
use App\Models\MaintenanceFeature;
use App\Models\MaintenancePeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The monthly cycle behind Maintenance Management.
 *
 * - Each calendar month (WIB) a contract runs in gets a period with the
 *   contract's quota at that time.
 * - When a month is over, its unused hours are handled by the month's
 *   leftover action (or the contract's policy): expire, roll over into the
 *   next month, or move into the development balance -- up to the balance
 *   cap, expiring after bank_expiry_months.
 * - The balance is spent oldest-expiry-first, so expiry always takes
 *   exactly the hours nobody used.
 *
 * sync() is idempotent and runs both from the daily scheduler and whenever
 * a contract is viewed, so a missed scheduler run never loses a month.
 */
class MaintenanceService
{
    public const TZ = 'Asia/Jakarta';

    public function today(): Carbon
    {
        return Carbon::now(self::TZ)->startOfDay();
    }

    /** Current month as "Y-m" (WIB). Months are compared as these strings
     * throughout, so stored dates (no time zone) and WIB "now" can't drift
     * apart by the 7-hour offset. */
    public function currentMonth(): string
    {
        return $this->today()->format('Y-m');
    }

    public function syncAll(): int
    {
        $count = 0;
        MaintenanceContract::query()->each(function (MaintenanceContract $contract) use (&$count) {
            $this->sync($contract);
            $count++;
        });

        return $count;
    }

    public function sync(MaintenanceContract $contract): void
    {
        DB::transaction(function () use ($contract) {
            // Serialises concurrent syncs (scheduler + a page view) per contract.
            $contract = MaintenanceContract::whereKey($contract->id)->lockForUpdate()->first();
            if (! $contract) {
                return;
            }
            $current = $this->currentMonth();

            // Close finished months oldest first; closing one opens the next
            // (when the contract ran then), so gaps are filled in order.
            while ($period = $contract->periods()->whereNull('closed_at')
                ->where('period_month', '<', $current.'-01')
                ->orderBy('period_month')->first()) {
                // Release what had already expired by then first, so a
                // catch-up over several months applies the cap in the
                // order things really happened.
                $this->expireBalance($contract, $period->period_month->copy()->startOfMonth()->addMonthNoOverflow());
                $this->closePeriod($contract, $period, $current);
            }

            if ($this->runsIn($contract, $current)) {
                $this->openPeriod($contract, $current);
            }

            $this->expireBalance($contract);
        });
    }

    /** Whether the contract should have a period for this "Y-m" month. */
    public function runsIn(MaintenanceContract $contract, string $month): bool
    {
        // Never back-fill months before the contract was entered here.
        $first = $contract->start_date->format('Y-m');
        if ($contract->created_at) {
            $first = max($first, $contract->created_at->copy()->timezone(self::TZ)->format('Y-m'));
        }

        return $contract->status === 'active'
            && $month >= $first
            && (! $contract->end_date || $month <= $contract->end_date->format('Y-m'));
    }

    public function openPeriod(MaintenanceContract $contract, string $month): MaintenancePeriod
    {
        // whereDate rather than firstOrCreate's plain equality: the date
        // cast stores a time part on some drivers.
        return $contract->periods()->whereDate('period_month', $month.'-01')->first()
            ?? $contract->periods()->create([
                'period_month' => $month.'-01',
                'quota_hours' => $contract->monthly_quota_hours,
                'carried_hours' => 0,
            ]);
    }

    /** The period that work done on this date counts against, if any. */
    public function periodFor(MaintenanceContract $contract, Carbon $date): ?MaintenancePeriod
    {
        $month = $date->format('Y-m');
        if ($month > $this->currentMonth()) {
            return null;
        }
        $period = $contract->periods()->whereDate('period_month', $month.'-01')->first();
        if (! $period && $month === $this->currentMonth() && $this->runsIn($contract, $month)) {
            $period = $this->openPeriod($contract, $month);
        }

        return $period;
    }

    private function closePeriod(MaintenanceContract $contract, MaintenancePeriod $period, string $current): void
    {
        $leftover = $period->totals()['remaining'];
        $action = $period->leftover_action ?? $contract->leftover_policy;
        $applied = $leftover > 0 ? $action : null;
        $banked = null;

        $next = $period->period_month->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m');
        $nextRuns = $next <= $current && $this->runsIn($contract, $next);
        $nextPeriod = $nextRuns ? $this->openPeriod($contract, $next) : null;

        if ($leftover > 0 && $action === 'bank') {
            $banked = $this->creditLeftover($contract, $period, $leftover);
        } elseif ($leftover > 0 && $action === 'rollover') {
            if ($nextPeriod) {
                $nextPeriod->increment('carried_hours', $leftover);
            } else {
                // Nothing to roll into (contract paused or ended).
                $applied = 'expire';
            }
        }

        $period->forceFill([
            'closed_at' => now(),
            'leftover_hours' => $leftover,
            'leftover_applied' => $applied,
            'leftover_banked_hours' => $banked,
        ])->save();
    }

    private function creditLeftover(MaintenanceContract $contract, MaintenancePeriod $period, float $leftover): float
    {
        $amount = $leftover;
        if ($contract->bank_cap_hours !== null) {
            $amount = max(0, min($leftover, round($contract->bank_cap_hours - $this->balance($contract), 2)));
        }

        $month = $period->period_month->copy()->locale('id')->translatedFormat('F Y');
        $note = "Sisa kuota {$month}";
        if ($amount < $leftover) {
            $note .= ' · '.$this->hours($leftover - $amount).' melebihi batas saldo, hangus';
        }

        if ($amount > 0) {
            $this->credit($contract, 'leftover', $amount, $this->expiryFrom($contract, $period->period_month, true), [
                'maintenance_period_id' => $period->id,
                'note' => $note,
            ]);
        }

        return round($amount, 2);
    }

    // ------------------------------------------------------------ balance

    public function balance(MaintenanceContract $contract): float
    {
        return round((float) $contract->bankEntries()->sum('hours'), 2);
    }

    /** Hours of the balance expiring within $days days, and the first date. */
    public function expiringSoon(MaintenanceContract $contract, int $days = 30): array
    {
        $credits = $contract->bankEntries()
            ->whereIn('type', MaintenanceBankEntry::CREDIT_TYPES)
            ->where('remaining_hours', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $this->today()->addDays($days)->toDateString())
            ->orderBy('expires_at')
            ->get();

        return [
            'hours' => round((float) $credits->sum('remaining_hours'), 2),
            'first_date' => $credits->first()?->expires_at?->toDateString(),
        ];
    }

    /**
     * When hours credited on $from expire: bank_expiry_months later, at the
     * end of that month for a month's leftover (October's leftover with 6
     * months lasts until 30 April), or on the same day for a manual credit.
     */
    public function expiryFrom(MaintenanceContract $contract, Carbon $from, bool $endOfMonth = false): ?Carbon
    {
        if (! $contract->bank_expiry_months) {
            return null;
        }

        return $endOfMonth
            ? $from->copy()->startOfMonth()->addMonthsNoOverflow($contract->bank_expiry_months)->endOfMonth()
            : $from->copy()->addMonthsNoOverflow($contract->bank_expiry_months)->endOfDay();
    }

    public function credit(MaintenanceContract $contract, string $type, float $hours, ?Carbon $expiresAt, array $attributes = []): MaintenanceBankEntry
    {
        return $contract->bankEntries()->create([
            'type' => $type,
            'hours' => round($hours, 2),
            'remaining_hours' => round($hours, 2),
            'expires_at' => $expiresAt?->toDateString(),
            ...$attributes,
        ]);
    }

    /**
     * Spends up to $hours from the balance, oldest expiry first (hours that
     * never expire last). Returns what was spent and the latest expiry among
     * the credits used (null if any of them never expires), so a later
     * refund can't outlive the hours it gives back.
     */
    public function debit(MaintenanceContract $contract, string $type, float $hours, array $attributes = []): array
    {
        $wanted = round(min($hours, $this->balance($contract)), 2);
        if ($wanted <= 0) {
            return ['hours' => 0.0, 'expires_at' => null];
        }

        $credits = $contract->bankEntries()
            ->whereIn('type', MaintenanceBankEntry::CREDIT_TYPES)
            ->where('remaining_hours', '>', 0)
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $left = $wanted;
        $latest = null;
        $neverExpires = false;
        foreach ($credits as $credit) {
            if ($left <= 0) {
                break;
            }
            $take = round(min($left, $credit->remaining_hours), 2);
            $credit->forceFill(['remaining_hours' => round($credit->remaining_hours - $take, 2)])->save();
            $left = round($left - $take, 2);

            if ($credit->expires_at === null) {
                $neverExpires = true;
            } elseif (! $latest || $credit->expires_at->gt($latest)) {
                $latest = $credit->expires_at;
            }
        }

        $spent = round($wanted - max(0, $left), 2);
        $contract->bankEntries()->create(['type' => $type, 'hours' => -$spent, ...$attributes]);

        return ['hours' => $spent, 'expires_at' => $neverExpires ? null : $latest];
    }

    /** Expires credits whose last day is before $asOf (default: today). */
    public function expireBalance(MaintenanceContract $contract, ?Carbon $asOf = null): void
    {
        $expired = $contract->bankEntries()
            ->whereIn('type', MaintenanceBankEntry::CREDIT_TYPES)
            ->where('remaining_hours', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', ($asOf ?? $this->today())->toDateString())
            ->get();

        foreach ($expired as $credit) {
            $hours = $credit->remaining_hours;
            $credit->forceFill(['remaining_hours' => 0])->save();
            $contract->bankEntries()->create([
                'type' => 'expire',
                'hours' => -$hours,
                'maintenance_period_id' => $credit->maintenance_period_id,
                'note' => 'Kedaluwarsa'.($credit->note ? ' · '.$credit->note : ''),
            ]);
        }
    }

    // ------------------------------------------------------------ features

    /** Reserves the estimate (or as much as the balance holds). */
    public function approveFeature(MaintenanceFeature $feature): void
    {
        DB::transaction(function () use ($feature) {
            $contract = MaintenanceContract::whereKey($feature->maintenance_contract_id)->lockForUpdate()->first();
            $this->expireBalance($contract);

            $reserved = $this->debit($contract, 'feature', $feature->estimated_hours, [
                'maintenance_feature_id' => $feature->id,
                'note' => 'Dipesan untuk fitur: '.$feature->title,
            ]);

            $feature->forceFill([
                'status' => 'approved',
                'reserved_hours' => $reserved['hours'],
                'reserved_expires_at' => $reserved['expires_at']?->toDateString(),
                'approved_at' => now(),
            ])->save();
        });
    }

    /**
     * Done or cancelled: unused reserved hours go back to the balance; hours
     * beyond the reservation come out of the balance while it lasts, and
     * the rest is marked billable.
     */
    public function settleFeature(MaintenanceFeature $feature, string $status): void
    {
        DB::transaction(function () use ($feature, $status) {
            $contract = MaintenanceContract::whereKey($feature->maintenance_contract_id)->lockForUpdate()->first();
            $used = $feature->usedHours();
            $reserved = $feature->reserved_hours;
            $billable = 0.0;

            if ($used < $reserved) {
                $back = round($reserved - $used, 2);
                $this->credit($contract, 'refund', $back, $feature->reserved_expires_at?->copy()->endOfDay(), [
                    'maintenance_feature_id' => $feature->id,
                    'note' => 'Sisa pesanan fitur dikembalikan: '.$feature->title,
                ]);
            } elseif ($used > $reserved) {
                $extra = round($used - $reserved, 2);
                $taken = $this->debit($contract, 'feature', $extra, [
                    'maintenance_feature_id' => $feature->id,
                    'note' => 'Kekurangan jam fitur: '.$feature->title,
                ])['hours'];
                $billable = round($extra - $taken, 2);
            }

            $feature->forceFill([
                'status' => $status,
                'billable_hours' => $billable,
                'completed_at' => now(),
            ])->save();

            $this->expireBalance($contract);
        });
    }

    // ------------------------------------------------------------ helpers

    public function hours(float $hours): string
    {
        $text = rtrim(rtrim(number_format($hours, 2, ',', '.'), '0'), ',');

        return $text.' jam';
    }
}
