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
        return $this->join(array_map(fn ($bytes) => StreamReader::createByString($bytes), $documents), $title);
    }

    /**
     * Same, from files on disk (large sets: the bytes never all sit in memory).
     *
     * @param  list<string>  $paths
     */
    public function mergeFiles(array $paths, string $title = ''): string
    {
        return $this->join($paths, $title);
    }

    private function join(array $sources, string $title): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        if ($title !== '') {
            $pdf->SetTitle($title, true);
        }
        $pdf->SetCreator('OPUS · Jendela Cakra Digital', true);

        foreach ($sources as $source) {
            $pages = $pdf->setSourceFile($source);
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
