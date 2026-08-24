<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class InvoiceController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['invoice-menu|invoice-list']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['invoice-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['invoice-edit']), only: ['update', 'markAsPaid', 'cancel']),
            new Middleware(PermissionMiddleware::using(['invoice-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Invoice::query()->with(['creator:id,name', 'project:id,name'])->orderByDesc('created_at');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $invoices = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Invoices Retrieved Successfully', $invoices, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function calculateTotals(array $items, ?float $ppnPercentage, ?float $adminFee): array
    {
        $subtotal = collect($items)->sum('total');
        $ppnPercentage = $ppnPercentage ?? 0;
        $ppnAmount = round($subtotal * ($ppnPercentage / 100), 2);
        $adminFee = $adminFee ?? 0;
        $total = $subtotal + $ppnAmount + $adminFee;

        return compact('subtotal', 'ppnAmount', 'total');
    }

    public function store(InvoiceStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $date = Carbon::parse($validated['date']);
            $totals = $this->calculateTotals($validated['items'], $validated['ppn_percentage'] ?? null, $validated['admin_fee'] ?? null);

            $invoice = Invoice::create([
                ...$validated,
                'invoice_number' => $this->numberService->generateInvoiceNumber($validated['client_code'], $date),
                'subtotal' => $totals['subtotal'],
                'ppn_percentage' => $validated['ppn_percentage'] ?? 0,
                'ppn_amount' => $totals['ppnAmount'],
                'admin_fee' => $validated['admin_fee'] ?? 0,
                'total' => $totals['total'],
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Invoice Created Successfully', $invoice, 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $invoice = Invoice::with(['creator:id,name', 'project:id,name', 'receipts'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Invoice Retrieved Successfully', $invoice, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Invoice Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(InvoiceUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $invoice = Invoice::findOrFail($id);

            if (isset($validated['items'])) {
                $totals = $this->calculateTotals(
                    $validated['items'],
                    $validated['ppn_percentage'] ?? $invoice->ppn_percentage,
                    $validated['admin_fee'] ?? $invoice->admin_fee
                );
                $validated['subtotal'] = $totals['subtotal'];
                $validated['ppn_amount'] = $totals['ppnAmount'];
                $validated['total'] = $totals['total'];
            }

            $invoice->update($validated);

            return ResponseHelper::jsonResponse(true, 'Invoice Updated Successfully', $invoice, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Invoice Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function markAsPaid(string $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            $invoice->update(['status' => 'paid']);

            return ResponseHelper::jsonResponse(true, 'Invoice Marked As Paid', $invoice, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Invoice Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function cancel(string $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            $invoice->update(['status' => 'cancelled']);

            return ResponseHelper::jsonResponse(true, 'Invoice Cancelled Successfully', $invoice, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Invoice Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            $invoice->delete();

            return ResponseHelper::jsonResponse(true, 'Invoice Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Invoice Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(string $id)
    {
        $invoice = Invoice::findOrFail($id);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $invoice->invoice_number).'.pdf');
    }
}
