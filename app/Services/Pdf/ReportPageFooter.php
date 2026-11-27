<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Footer of the company reports: the document name on the left and
 * "Halaman X dari Y" on the right of every page, drawn on the canvas
 * (dompdf only knows the page count once everything is laid out).
 */
class ReportPageFooter
{
    public static function apply(DomPdf $pdf, string $label): DomPdf
    {
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $left = 18 * 72 / 25.4;
        $right = $canvas->get_width() - $left;
        $y = $canvas->get_height() - 12 * 72 / 25.4;
        $grey = [0x6B / 255, 0x72 / 255, 0x80 / 255];
        $rule = [0xCB / 255, 0xD2 / 255, 0xD9 / 255];

        $canvas->page_script(function (int $page, int $pages, $canvas, $fontMetrics) use ($label, $left, $right, $y, $grey, $rule) {
            $font = $fontMetrics->getFont('helvetica');
            // dompdf caches the last fill colour and skips setting one it
            // thinks is current, so the footer could take the page's last
            // text colour (e.g. orange "Dalam proses"). Two tiny fills in
            // different colours force the real colour to be written.
            $canvas->filled_rectangle($left, $y + 2, 0.01, 0.01, [0, 0, 0]);
            $canvas->filled_rectangle($left, $y + 2, 0.01, 0.01, [1, 1, 1]);
            $canvas->line($left, $y - 5, $right, $y - 5, $rule, 0.4);
            $canvas->text($left, $y, $label, $font, 7, $grey);
            $text = "Halaman {$page} dari {$pages}";
            $canvas->text($right - $fontMetrics->getTextWidth($text, $font, 7), $y, $text, $font, 7, $grey);
        });

        return $pdf;
    }
}
