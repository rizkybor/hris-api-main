<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\PurchaseOrderStoreRequest;
use App\Http\Requests\PurchaseOrderUpdateRequest;
use App\Models\PurchaseOrder;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['purchase-order-menu|purchase-order-list']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['purchase-order-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['purchase-order-edit']), only: ['update', 'cancel']),
            new Middleware(PermissionMiddleware::using(['purchase-order-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = PurchaseOrder::query()->with('creator:id,name')->orderByDesc('created_at');

            if ($request->search) {
                $query->search($request->search);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $orders = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Purchase Orders Retrieved Successfully', $orders, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(PurchaseOrderStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $date = Carbon::parse($validated['date']);
            $total = collect($validated['items'])->sum('price');

            $order = PurchaseOrder::create([
                ...$validated,
                'po_number' => $this->numberService->generatePoNumber($validated['type'], $date),
                'total' => $total,
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Purchase Order Created Successfully', $order, 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $order = PurchaseOrder::with('creator:id,name')->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Purchase Order Retrieved Successfully', $order, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Purchase Order Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(PurchaseOrderUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $order = PurchaseOrder::findOrFail($id);

            if (isset($validated['items'])) {
                $validated['total'] = collect($validated['items'])->sum('price');
            }

            $order->update($validated);

            return ResponseHelper::jsonResponse(true, 'Purchase Order Updated Successfully', $order, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Purchase Order Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function cancel(string $id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);
            $order->update(['status' => 'cancelled']);

            return ResponseHelper::jsonResponse(true, 'Purchase Order Cancelled Successfully', $order, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Purchase Order Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);
            $order->delete();

            return ResponseHelper::jsonResponse(true, 'Purchase Order Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Purchase Order Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(string $id)
    {
        $order = PurchaseOrder::findOrFail($id);

        $pdf = Pdf::loadView('pdf.purchase-order', ['order' => $order])
            ->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $order->po_number).'.pdf');
    }
}
