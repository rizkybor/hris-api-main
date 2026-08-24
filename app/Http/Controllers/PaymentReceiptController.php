<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\PaymentReceiptStoreRequest;
use App\Http\Requests\PaymentReceiptUpdateRequest;
use App\Models\PaymentReceipt;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PaymentReceiptController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payment-receipt-menu|payment-receipt-list']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['payment-receipt-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['payment-receipt-edit']), only: ['update', 'cancel']),
            new Middleware(PermissionMiddleware::using(['payment-receipt-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = PaymentReceipt::query()->with(['creator:id,name', 'invoice:id,invoice_number'])->orderByDesc('created_at');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $receipts = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Payment Receipts Retrieved Successfully', $receipts, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(PaymentReceiptStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $date = Carbon::parse($validated['date']);

            $receiptNumber = $validated['numbering_mode'] === 'manual'
                ? $validated['receipt_number']
                : $this->numberService->generateReceiptNumber($validated['client_code'], $date);

            unset($validated['numbering_mode'], $validated['receipt_number']);

            $receipt = PaymentReceipt::create([
                ...$validated,
                'receipt_number' => $receiptNumber,
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Payment Receipt Created Successfully', $receipt, 201);
        } catch (QueryException $e) {
            // Unique index on receipt_number is the real backstop against
            // duplicates (e.g. a manual number colliding with one the
            // automatic sequence later generates, or two concurrent manual
            // submissions racing past the FormRequest's unique check) --
            // surface it as a normal validation-style error instead of a
            // raw 500 so the user knows to just retry.
            if ((int) $e->getCode() === 23000) {
                return ResponseHelper::jsonResponse(false, 'This receipt number is already in use. Please try again.', null, 422);
            }

            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $receipt = PaymentReceipt::with(['creator:id,name', 'invoice:id,invoice_number'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Payment Receipt Retrieved Successfully', $receipt, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payment Receipt Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(PaymentReceiptUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $receipt = PaymentReceipt::findOrFail($id);
            $receipt->update($validated);

            return ResponseHelper::jsonResponse(true, 'Payment Receipt Updated Successfully', $receipt, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payment Receipt Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function cancel(string $id)
    {
        try {
            $receipt = PaymentReceipt::findOrFail($id);
            $receipt->update(['status' => 'cancelled']);

            return ResponseHelper::jsonResponse(true, 'Payment Receipt Cancelled Successfully', $receipt, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payment Receipt Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $receipt = PaymentReceipt::findOrFail($id);
            $receipt->delete();

            return ResponseHelper::jsonResponse(true, 'Payment Receipt Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Payment Receipt Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(string $id)
    {
        $receipt = PaymentReceipt::with('invoice:id,invoice_number')->findOrFail($id);

        $pdf = Pdf::loadView('pdf.payment-receipt', ['receipt' => $receipt])
            ->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $receipt->receipt_number).'.pdf');
    }
}
