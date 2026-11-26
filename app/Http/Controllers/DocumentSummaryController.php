<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Totals for the recap above the Invoice, Kuitansi and Purchase Order
 * lists, for a period (from/to, by document date; both optional = all
 * time). Each needs the same permission as its list.
 */
class DocumentSummaryController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['invoice-menu|invoice-list']), only: ['invoices']),
            new Middleware(PermissionMiddleware::using(['payment-receipt-menu|payment-receipt-list']), only: ['paymentReceipts']),
            new Middleware(PermissionMiddleware::using(['purchase-order-menu|purchase-order-list']), only: ['purchaseOrders']),
        ];
    }

    public function invoices(Request $request)
    {
        $base = $this->period(Invoice::query(), $request);
        $sum = fn (Builder $query) => round((float) $query->sum('total'), 2);

        $billed = (clone $base)->where('status', '!=', 'cancelled');
        $paid = (clone $base)->where('status', 'paid');
        $unpaid = (clone $base)->where('status', 'unpaid');

        return ResponseHelper::jsonResponse(true, 'Invoice Summary Retrieved Successfully', [
            'billed' => $sum($billed),
            'billed_count' => (clone $billed)->count(),
            'paid' => $sum($paid),
            'paid_count' => (clone $paid)->count(),
            'unpaid' => $sum($unpaid),
            'unpaid_count' => (clone $unpaid)->count(),
            'cancelled_count' => (clone $base)->where('status', 'cancelled')->count(),
        ], 200);
    }

    public function paymentReceipts(Request $request)
    {
        // amount is what was received (net of PPh 23); gross = amount + PPh 23.
        $active = $this->period(PaymentReceipt::query(), $request)->where('status', 'active');
        $net = round((float) (clone $active)->sum('amount'), 2);
        $pph23 = round((float) (clone $active)->sum('pph23_amount'), 2);

        return ResponseHelper::jsonResponse(true, 'Payment Receipt Summary Retrieved Successfully', [
            'received' => $net,
            'pph23' => $pph23,
            'gross' => round($net + $pph23, 2),
            'count' => (clone $active)->count(),
            'cancelled_count' => $this->period(PaymentReceipt::query(), $request)->where('status', 'cancelled')->count(),
        ], 200);
    }

    public function purchaseOrders(Request $request)
    {
        $active = $this->period(PurchaseOrder::query(), $request)->where('status', 'active');
        $sum = fn (Builder $query) => round((float) $query->sum('total'), 2);

        return ResponseHelper::jsonResponse(true, 'Purchase Order Summary Retrieved Successfully', [
            'total' => $sum(clone $active),
            'count' => (clone $active)->count(),
            'external' => $sum((clone $active)->where('type', 'E')),
            'external_count' => (clone $active)->where('type', 'E')->count(),
            'internal' => $sum((clone $active)->where('type', 'I')),
            'internal_count' => (clone $active)->where('type', 'I')->count(),
            'cancelled_count' => $this->period(PurchaseOrder::query(), $request)->where('status', 'cancelled')->count(),
        ], 200);
    }

    /** from / to (Y-m-d, inclusive) on the document's own date. */
    private function period(Builder $query, Request $request): Builder
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        // whereDate: date columns may be stored with a 00:00:00 time part.
        return $query
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to));
    }
}
