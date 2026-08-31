<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LetterStoreRequest;
use App\Http\Requests\LetterUpdateRequest;
use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LetterController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['letter-menu|letter-list']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['letter-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['letter-edit']), only: ['update', 'cancel']),
            new Middleware(PermissionMiddleware::using(['letter-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Letter::query()->with(['letterCode', 'divisionCode', 'creator:id,name', 'employee.user'])->orderByDesc('created_at');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $letters = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Letters Retrieved Successfully', $letters, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(LetterStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $date = Carbon::parse($validated['date']);
            $letterCode = LetterCode::findOrFail($validated['letter_code_id']);
            $divisionCode = DivisionCode::findOrFail($validated['division_code_id']);

            $generated = $this->numberService->generateLetterNumber(
                $letterCode->code,
                $validated['type'],
                $divisionCode->code,
                $date
            );

            $letter = Letter::create([
                ...$validated,
                'letter_number' => $generated['number'],
                'sequence' => $generated['sequence'],
                'year' => $generated['year'],
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Letter Created Successfully', $letter->load(['letterCode', 'divisionCode']), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $letter = Letter::with(['letterCode', 'divisionCode', 'creator:id,name'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Letter Retrieved Successfully', $letter, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(LetterUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $letter = Letter::findOrFail($id);
            $letter->update($validated);

            return ResponseHelper::jsonResponse(true, 'Letter Updated Successfully', $letter->load(['letterCode', 'divisionCode']), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Cancelled letters keep their number reserved and are labeled "DIBATALKAN" —
     * they are never deleted or renumbered, per company numbering policy.
     */
    public function cancel(string $id)
    {
        try {
            $letter = Letter::findOrFail($id);
            $letter->update(['status' => 'cancelled']);

            return ResponseHelper::jsonResponse(true, 'Letter Cancelled Successfully', $letter, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $letter = Letter::findOrFail($id);
            $letter->delete();

            return ResponseHelper::jsonResponse(true, 'Letter Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(string $id)
    {
        $letter = Letter::with(['letterCode', 'divisionCode'])->findOrFail($id);

        $view = $letter->letterCode?->code === 'BAST' ? 'pdf.bast' : 'pdf.letter';

        $pdf = Pdf::loadView($view, ['letter' => $letter])
            ->setPaper('a4');

        if ($view === 'pdf.letter') {
            $this->addContinuationMarkers($pdf, $letter);
        }

        return $pdf->stream(str_replace('/', '-', $letter->letter_number).'.pdf');
    }

    /**
     * Multi-page rule for letter.blade.php's two letterhead templates
     * (Primary/Secondary): page 1 gets a small "Halaman 1 dari N" note in
     * the bottom-right corner (only when the letter actually spans more
     * than one page), and every page 2+ gets a "Sambungan <Letter Code>
     * No. ... Tanggal ..." note top-right instead. Drawn directly on the
     * PDF canvas via Dompdf's page_script callback rather than in the
     * Blade view's CSS, because dompdf has no reliable way to target
     * "page 1 only" vs "page 2+" content in CSS (@page :first is ignored
     * here -- see letter.blade.php's own notes) but page_script's callback
     * receives the real, correct page number/count per page. A Closure is
     * passed (not a string) so this never goes through eval() -- it doesn't
     * need config('dompdf.options.enable_php'), which stays off since the
     * letter body itself is user-authored HTML.
     */
    private function addContinuationMarkers($pdf, Letter $letter): void
    {
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        // Matches letter.blade.php's .page padding-right (22mm) so this
        // text's right edge lines up with the body content's own margin.
        $rightEdge = $canvas->get_width() - (22 * 72 / 25.4);
        // Shared by both the page-1 "Halaman X dari Y" note and the page
        // 2+ continuation header -- small and soft so neither competes
        // with the letter's actual content.
        $markerGray = [0x99 / 255, 0x99 / 255, 0x99 / 255];
        $markerSize = 6;

        $letterCodeName = $letter->letterCode->name ?? 'Surat';
        $tanggal = $letter->date->locale('id')->translatedFormat('d F Y');
        $continuationLine1 = "... {$letterCodeName} No. {$letter->letter_number} - {$tanggal}";

        // Primary's continuation header stays top-right, clearing the
        // letterhead artwork below the logo/divider line -- unchanged, per
        // explicit request. Secondary instead goes bottom-right (753/765,
        // the same two lines' worth of clearance the page-1 "Halaman X dari
        // Y" note already uses at 765, just stacked above it), per explicit
        // request to move it off the top for that template only.
        $isSecondary = ($letter->template ?: 'primary') === 'secondary';
        $continuationLine1Y = $isSecondary ? 753 : 90;
        $continuationLine2Y = $isSecondary ? 765 : 102;

        $canvas->page_script(
            function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use (
                $rightEdge, $markerGray, $markerSize,
                $continuationLine1, $continuationLine1Y, $continuationLine2Y
            ) {
                if ($pageNumber === 1) {
                    if ($pageCount <= 1) {
                        return;
                    }

                    $font = $fontMetrics->getFont('helvetica', 'italic');
                    $text = "- Page {$pageNumber} of {$pageCount} - ...";
                    $width = $fontMetrics->getTextWidth($text, $font, $markerSize);
                    $canvas->text($rightEdge - $width, 765, $text, $font, $markerSize, $markerGray);

                    return;
                }

                $font = $fontMetrics->getFont('helvetica', 'italic');
                $line2 = "- Page {$pageNumber} of {$pageCount} -";

                $width1 = $fontMetrics->getTextWidth($continuationLine1, $font, $markerSize);
                $width2 = $fontMetrics->getTextWidth($line2, $font, $markerSize);

                $canvas->text($rightEdge - $width1, $continuationLine1Y, $continuationLine1, $font, $markerSize, $markerGray);
                $canvas->text($rightEdge - $width2, $continuationLine2Y, $line2, $font, $markerSize, $markerGray);
            }
        );
    }
}
