<?php

namespace App\Services\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Joins several PDFs (as raw bytes, e.g. two dompdf renders) into one,
 * page by page, keeping each page's own size and orientation.
 */
class PdfMerger
{
    /**
     * @param  list<string>  $documents  raw PDF bytes, in order
     */
    public function merge(array $documents, string $title = ''): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        if ($title !== '') {
            $pdf->SetTitle($title, true);
        }
        $pdf->SetCreator('OPUS · Jendela Cakra Digital', true);

        foreach ($documents as $bytes) {
            $pages = $pdf->setSourceFile(StreamReader::createByString($bytes));
            for ($page = 1; $page <= $pages; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        return $pdf->Output('S');
    }
}
