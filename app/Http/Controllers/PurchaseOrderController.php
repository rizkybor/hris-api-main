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

            if ($request->status) {
                $query->where('status', $request->status);
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

        $this->addContinuationMarkers($pdf, $order);

        return $pdf->stream(str_replace('/', '-', $order->po_number).'.pdf');
    }

    /**
     * Same multi-page continuation marker as LetterController's Secondary
     * template: page 1 gets a small bottom-right note when the PO spans
     * more than one page, and every page 2+ gets a two-line bottom-right
     * note instead. Bottom-right (not top-right) because this document
     * always uses the secondary letterhead artwork. Drawn directly on the
     * canvas via Dompdf's page_script Closure -- see LetterController's own
     * notes for why (no config('dompdf.options.enable_php') needed, and
     * dompdf has no reliable "page 1 only" vs "page 2+" CSS selector).
     */
    private function addContinuationMarkers($pdf, PurchaseOrder $order): void
    {
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        // Matches purchase-order.blade.php's .page padding-right (22mm) so
        // this text's right edge lines up with the body content's own margin.
        $rightEdge = $canvas->get_width() - (22 * 72 / 25.4);
        $markerGray = [0x99 / 255, 0x99 / 255, 0x99 / 255];
        $markerSize = 6;

        $tanggal = $order->date->translatedFormat('d F Y');
        $continuationLine1 = "... Purchase Order No. {$order->po_number} - {$tanggal}";

        // Bottom-right, same physical spot as letter.blade.php's Secondary
        // template (753/765) -- the letterhead image is pinned to the same
        // absolute page position across every document that uses it, since
        // .letterhead's top offset always cancels out its own @page margin,
        // so that footer clearance is reusable here unchanged.
        $continuationLine1Y = 753;
        $continuationLine2Y = 765;

        $canvas->page_script(
            function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use (
                $rightEdge, $markerGray, $markerSize,
                $continuationLine1, $continuationLine1Y, $continuationLine2Y
            ) {
                $font = $fontMetrics->getFont('helvetica', 'italic');

                if ($pageNumber === 1) {
                    if ($pageCount <= 1) {
                        return;
                    }

                    $text = "- Page {$pageNumber} of {$pageCount} - ...";
                    $width = $fontMetrics->getTextWidth($text, $font, $markerSize);
                    $canvas->text($rightEdge - $width, 765, $text, $font, $markerSize, $markerGray);

                    return;
                }

                $line2 = "- Page {$pageNumber} of {$pageCount} -";
                $width1 = $fontMetrics->getTextWidth($continuationLine1, $font, $markerSize);
                $width2 = $fontMetrics->getTextWidth($line2, $font, $markerSize);

                $canvas->text($rightEdge - $width1, $continuationLine1Y, $continuationLine1, $font, $markerSize, $markerGray);
                $canvas->text($rightEdge - $width2, $continuationLine2Y, $line2, $font, $markerSize, $markerGray);
            }
        );
    }
}
