<?php

namespace App\Services\Pdf;

use App\Models\Letter;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders a Letter (Surat) to PDF with its letterhead: BAST has its own
 * layout, every other code uses letter.blade.php plus the page markers
 * below. Shared by the Letters page and the project progress export
 * (cover letter + report in one PDF).
 */
class LetterPdfService
{
    public function make(Letter $letter): DomPdf
    {
        $letter->loadMissing(['letterCode', 'divisionCode']);
        $view = $letter->letterCode?->code === 'BAST' ? 'pdf.bast' : 'pdf.letter';

        $pdf = Pdf::loadView($view, ['letter' => $letter])->setPaper('a4');

        if ($view === 'pdf.letter') {
            $this->addContinuationMarkers($pdf, $letter);
        }

        return $pdf;
    }

    public static function filename(Letter $letter): string
    {
        return str_replace('/', '-', $letter->letter_number).'.pdf';
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
