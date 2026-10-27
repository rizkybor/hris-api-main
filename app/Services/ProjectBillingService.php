<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Project value vs what was invoiced for the project vs what was paid on
 * those invoices (payment receipts). A receipt settles its gross amount --
 * net received plus any PPh 23 the client withheld. Cancelled invoices and
 * receipts don't count. Used by the project detail billing panel, the
 * Project report and its export.
 */
class ProjectBillingService
{
    /** @return Collection<int, Collection<int, Invoice>> invoices keyed by project id */
    public function invoicesFor(array $projectIds): Collection
    {
        return Invoice::whereIn('project_id', $projectIds)
            ->with(['receipts' => fn ($q) => $q->where('status', '!=', 'cancelled')->orderBy('date')])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->groupBy('project_id');
    }

    public function invoiceRow(Invoice $invoice): array
    {
        $cancelled = $invoice->status === 'cancelled';
        $total = (float) $invoice->total;
        $paid = round($invoice->receipts->sum(fn ($r) => (float) $r->amount + (float) $r->pph23_amount), 2);
        $outstanding = $cancelled ? 0.0 : round($total - $paid, 2);

        $state = match (true) {
            $cancelled => 'cancelled',
            $paid <= 0 => 'unpaid',
            $outstanding > 0 => 'partial',
            $outstanding < 0 => 'overpaid',
            default => 'paid',
        };

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'date' => $invoice->date?->toDateString(),
            'billing_period' => $invoice->billing_period,
            'subtotal' => (float) $invoice->subtotal,
            'total' => $total,
            'invoice_status' => $invoice->status,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'state' => $state,
            // The invoice's own Paid/Unpaid flag disagreeing with its receipts.
            'status_mismatch' => ! $cancelled && (($invoice->status === 'paid') !== in_array($state, ['paid', 'overpaid'], true)),
            'receipts' => $invoice->receipts->map(fn ($r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'date' => $r->date?->toDateString(),
                'amount' => (float) $r->amount,
                'pph23_amount' => (float) $r->pph23_amount,
                'payment_status' => $r->payment_status,
            ])->values(),
        ];
    }

    /** Totals and verdict for one project; 'invoices' holds the per-invoice rows. */
    public function summary(float $projectValue, Collection $invoices): array
    {
        $rows = $invoices->map(fn (Invoice $i) => $this->invoiceRow($i))->values();
        $active = $rows->where('state', '!=', 'cancelled');
        $invoicedDpp = round($active->sum('subtotal'), 2);
        $invoiced = round($active->sum('total'), 2);
        $paid = round($active->sum('paid'), 2);
        $outstanding = round($invoiced - $paid, 2);

        return [
            'project_value' => $projectValue,
            'invoiced_subtotal' => $invoicedDpp,
            'invoiced_total' => $invoiced,
            'paid_total' => $paid,
            'pph23_withheld' => round($active->sum(fn ($r) => collect($r['receipts'])->sum('pph23_amount')), 2),
            'outstanding' => $outstanding,
            // Project value not invoiced yet (before tax, like the project value).
            'not_invoiced' => round(max(0, $projectValue - $invoicedDpp), 2),
            'invoiced_percent' => $projectValue > 0 ? round($invoicedDpp / $projectValue * 100, 1) : null,
            'paid_percent' => $invoiced > 0 ? round($paid / $invoiced * 100, 1) : null,
            'status' => match (true) {
                $invoiced <= 0 => 'not_invoiced',
                abs($outstanding) < 1 => 'settled',
                $outstanding > 0 => 'outstanding',
                default => 'overpaid',
            },
            'invoice_count' => $active->count(),
            'mismatch_count' => $rows->where('status_mismatch', true)->count(),
            'invoices' => $rows,
        ];
    }
}
