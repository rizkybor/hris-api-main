<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Project detail > billing: the project's value, what has been invoiced for
 * it, what has been paid against those invoices (payment receipts), and
 * whether the two match. A receipt settles its gross amount -- net received
 * plus any PPh 23 the client withheld.
 */
class ProjectBillingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-expense-list']), only: ['show']),
        ];
    }

    public function show(int $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return ResponseHelper::jsonResponse(false, 'Project Not Found', null, 404);
        }

        $invoices = Invoice::where('project_id', $project->id)
            ->with(['receipts' => fn ($q) => $q->where('status', '!=', 'cancelled')->orderBy('date')])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) {
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

            // The invoice's own Paid/Unpaid flag disagreeing with its receipts.
            $mismatch = ! $cancelled && (($invoice->status === 'paid') !== in_array($state, ['paid', 'overpaid'], true));

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
                'status_mismatch' => $mismatch,
                'receipts' => $invoice->receipts->map(fn ($r) => [
                    'id' => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'date' => $r->date?->toDateString(),
                    'amount' => (float) $r->amount,
                    'pph23_amount' => (float) $r->pph23_amount,
                    'payment_status' => $r->payment_status,
                ])->values(),
            ];
        });

        $active = $rows->where('state', '!=', 'cancelled');
        $value = (float) $project->budget;
        $invoicedDpp = round($active->sum('subtotal'), 2);
        $invoiced = round($active->sum('total'), 2);
        $paid = round($active->sum('paid'), 2);
        $outstanding = round($invoiced - $paid, 2);

        return ResponseHelper::jsonResponse(true, 'Project Billing Retrieved Successfully', [
            'project_value' => $value,
            'invoiced_subtotal' => $invoicedDpp,
            'invoiced_total' => $invoiced,
            'paid_total' => $paid,
            'pph23_withheld' => round($active->sum(fn ($r) => collect($r['receipts'])->sum('pph23_amount')), 2),
            'outstanding' => $outstanding,
            // Project value not invoiced yet (before tax, like the project value).
            'not_invoiced' => round(max(0, $value - $invoicedDpp), 2),
            'invoiced_percent' => $value > 0 ? round($invoicedDpp / $value * 100, 1) : null,
            'paid_percent' => $invoiced > 0 ? round($paid / $invoiced * 100, 1) : null,
            'status' => match (true) {
                $invoiced <= 0 => 'not_invoiced',
                abs($outstanding) < 1 => 'settled',
                $outstanding > 0 => 'outstanding',
                default => 'overpaid',
            },
            'mismatch_count' => $rows->where('status_mismatch', true)->count(),
            'invoices' => $rows->values(),
        ], 200);
    }
}
