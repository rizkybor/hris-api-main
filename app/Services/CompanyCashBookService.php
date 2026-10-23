<?php

namespace App\Services;

use App\Models\CompanyCashBalance;
use App\Models\CompanyCashTransaction;
use Illuminate\Support\Collection;

/**
 * The company cash book with its running balance. The balance always walks
 * the FULL chronological history from the opening balance, so each row's
 * running_balance is the true company-wide total at that point -- filters
 * only narrow which rows are returned. Shared by the Cash Book page, the
 * Finance report and its export.
 */
class CompanyCashBookService
{
    /**
     * @param  array{project_id?: int|string|null, type?: string|null, source?: string|null, start_date?: string|null, end_date?: string|null, search?: string|null}  $filters
     * @return array{balance_row: CompanyCashBalance, opening_balance: float, closing_balance: float, period_opening: float, period_closing: float, rows: Collection}
     */
    public function ledger(array $filters = []): array
    {
        $all = CompanyCashTransaction::with(['creator', 'project'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $balanceRow = CompanyCashBalance::current();
        $opening = (float) $balanceRow->opening_balance;
        $running = $opening;

        // Indonesian "buku kas" convention: Debit = uang masuk, Kredit =
        // uang keluar/pemakaian -- matches the project cash ledger.
        foreach ($all as $transaction) {
            $amount = (float) $transaction->amount;
            $running += $transaction->type === 'debit' ? $amount : -$amount;
            $transaction->running_balance = $running;
        }

        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        $date = fn ($t) => $t->transaction_date->toDateString();

        // Balance just before / at the end of the date range (company-wide).
        $before = $start ? $all->filter(fn ($t) => $date($t) < $start)->last() : null;
        $atEnd = $end ? $all->filter(fn ($t) => $date($t) <= $end)->last() : $all->last();

        $rows = $all;
        if (! empty($filters['project_id'])) {
            $rows = $rows->where('project_id', (int) $filters['project_id']);
        }
        if (! empty($filters['type'])) {
            $rows = $rows->where('type', $filters['type']);
        }
        if (! empty($filters['source'])) {
            $rows = $rows->filter(fn ($t) => $t->isSynced() === ($filters['source'] === 'project'));
        }
        if ($start) {
            $rows = $rows->filter(fn ($t) => $date($t) >= $start);
        }
        if ($end) {
            $rows = $rows->filter(fn ($t) => $date($t) <= $end);
        }
        if (! empty($filters['search'])) {
            $needle = mb_strtolower($filters['search']);
            $rows = $rows->filter(fn ($t) => str_contains(mb_strtolower($t->description.' '.$t->notes.' '.$t->project?->name), $needle));
        }

        return [
            'balance_row' => $balanceRow,
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'period_opening' => $before ? (float) $before->running_balance : $opening,
            'period_closing' => $atEnd ? (float) $atEnd->running_balance : $opening,
            'rows' => $rows->values(),
        ];
    }
}
