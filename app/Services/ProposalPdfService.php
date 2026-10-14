<?php

namespace App\Services;

use App\Models\Flowchart;
use App\Models\Proposal;
use App\Services\Cloudinary\CloudinaryUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Renders a Proposal Penawaran to PDF -- shared by the authenticated export
 * and the public share link.
 */
class ProposalPdfService
{
    /** The proposal's PDF as an inline browser response. */
    public function response(Proposal $proposal)
    {
        $tempFiles = [];

        try {
            // dompdf can't fetch remote images (enable_remote is off on
            // purpose), so the cover and every image inside the body are
            // pulled down from our Cloudinary account into storage first.
            // Images from anywhere else are dropped.
            $coverPath = $proposal->cover_image
                ? $this->downloadImage(CloudinaryUrl::image($proposal->cover_image), $tempFiles)
                : null;

            // Flowcharts inserted into the proposal point at their source
            // flowchart; use its latest snapshot so edits made in the
            // Flowchart module show up without re-inserting the image.
            $flowchartPreviews = $this->flowchartPreviews($proposal->sections ?? []);

            $sections = array_map(function ($section) use (&$tempFiles, $flowchartPreviews) {
                $section['content'] = preg_replace_callback(
                    '/<img\b[^>]*?\bsrc="([^"]*)"[^>]*>/i',
                    function ($match) use (&$tempFiles, $flowchartPreviews) {
                        $src = html_entity_decode($match[1]);
                        if (preg_match('/\bdata-flowchart-id="(\d+)"/', $match[0], $id) && isset($flowchartPreviews[(int) $id[1]])) {
                            $src = $flowchartPreviews[(int) $id[1]];
                        }
                        $local = $this->isOwnCloudinaryUrl($src) ? $this->downloadImage($src, $tempFiles) : null;

                        return $local ? str_replace($match[1], $local, $match[0]) : '';
                    },
                    $section['content'] ?? ''
                );

                return $section;
            }, $proposal->sections ?? []);

            $pdf = Pdf::loadView('pdf.proposal', [
                'proposal' => $proposal,
                'sections' => $sections,
                'coverPath' => $coverPath,
            ])->setPaper('a4');

            $this->addPageChrome($pdf, $proposal);

            $output = $pdf->output();
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }

        $filename = Str::slug($proposal->documentTitle()) ?: 'proposal-penawaran';

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'.pdf"',
        ]);
    }

    /**
     * Running header and footer on every content page (the cover, page 1,
     * stays clean): company name / document label with a thin rule at the
     * top, and the document title / "Halaman x dari y" at the bottom.
     */
    private function addPageChrome($pdf, Proposal $proposal): void
    {
        // Through the wrapper (not Dompdf directly), so output() knows it's
        // already rendered and doesn't render again, dropping the script.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $metrics = $dompdf->getFontMetrics();
        $canvas = $dompdf->getCanvas();

        $regular = $metrics->getFont('Helvetica');
        $bold = $metrics->getFont('Helvetica', 'bold');
        $navy = [0.04, 0.11, 0.32];
        $muted = [0.39, 0.45, 0.55];
        $rule = [0.84, 0.86, 0.90];
        $margin = 62; // ~22mm, matches the page's side margins
        $size = 7.5;

        $headerRight = 'Proposal Penawaran · '.$proposal->client_name;
        $footerLeft = $proposal->documentTitle();
        $maxWidth = $canvas->get_width() - $margin * 2 - 90;
        foreach (['headerRight', 'footerLeft'] as $var) {
            while ($metrics->getTextWidth($$var, $regular, $size) > $maxWidth && mb_strlen($$var) > 10) {
                $$var = mb_substr($$var, 0, -4).'…';
            }
        }

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($regular, $bold, $navy, $muted, $rule, $margin, $size, $headerRight, $footerLeft) {
            if ($pageNumber === 1) {
                return;
            }
            $width = $canvas->get_width();
            $height = $canvas->get_height();

            // Header
            $canvas->text($margin, 34, 'PT JENDELA CAKRA DIGITAL', $bold, $size, $navy);
            $canvas->text($width - $margin - $fontMetrics->getTextWidth($headerRight, $regular, $size), 34, $headerRight, $regular, $size, $muted);
            $canvas->line($margin, 48, $width - $margin, 48, $rule, 0.6);

            // Footer
            $y = $height - 40;
            $canvas->line($margin, $y - 8, $width - $margin, $y - 8, $rule, 0.6);
            $canvas->text($margin, $y, $footerLeft, $regular, $size, $muted);
            $pageLabel = "Halaman {$pageNumber} dari {$pageCount}";
            $canvas->text($width - $margin - $fontMetrics->getTextWidth($pageLabel, $regular, $size), $y, $pageLabel, $regular, $size, $muted);
        });
    }

    /** [flowchart id => latest preview URL] for flowcharts used in the sections. */
    private function flowchartPreviews(array $sections): array
    {
        preg_match_all('/\bdata-flowchart-id="(\d+)"/', implode('', array_column($sections, 'content')), $matches);
        $ids = array_unique(array_map('intval', $matches[1] ?? []));
        if (! $ids) {
            return [];
        }

        return Flowchart::whereIn('id', $ids)
            ->whereNotNull('preview_image')
            ->pluck('preview_image', 'id')
            ->map(fn ($publicId) => CloudinaryUrl::image($publicId))
            ->all();
    }

    private function isOwnCloudinaryUrl(string $url): bool
    {
        $cloud = config('services.cloudinary.cloud_name');

        return $cloud && str_starts_with(html_entity_decode($url), "https://res.cloudinary.com/{$cloud}/");
    }

    /**
     * Downloads into storage/app/temp -- inside dompdf's chroot (the
     * project directory), which a system temp dir like /tmp is not.
     */
    private function downloadImage(?string $url, array &$tempFiles): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $dir = storage_path('app/temp');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Ask Cloudinary for a print-sized JPEG instead of the original
            // upload (photos straight off a camera can be 6000px / 10MB),
            // which keeps the PDF small and fast to render.
            $url = preg_replace('#/image/upload/(?!w_)#', '/image/upload/w_1600,c_limit,q_80,f_jpg/', $url, 1);

            $path = $dir.'/proposal-'.Str::random(16);
            file_put_contents($path, Http::timeout(20)->get($url)->throw()->body());
            $tempFiles[] = $path;

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Proposal PDF image download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
