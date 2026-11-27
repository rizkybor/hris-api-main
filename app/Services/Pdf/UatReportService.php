<?php

namespace App\Services\Pdf;

use App\Models\ProjectUatSession;
use App\Services\Cloudinary\CloudinaryUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UAT documents for the client, per UAT:
 * - result ("Laporan Hasil UAT"): sessions and every finding/note found;
 * - revision ("Laporan Hasil Revisi UAT"): how each was revised/updated,
 *   with dates and proof photos, for the client to re-check.
 * Both end with a sign-off block for both parties.
 */
class UatReportService
{
    /** Proof photos fetched small (the PDF stays light). */
    private const PHOTO_TRANSFORM = 'w_500,h_500,c_limit,q_70,f_jpg';

    /**
     * @return array{0: DomPdf, 1: list<string>} the PDF and temp files to delete after output
     */
    public const KINDS = ['result', 'revision'];

    public function make(ProjectUatSession $uat, string $kind = 'result'): array
    {
        $uat->loadMissing(['project.client', 'project.projectLeader.user', 'locations.items.resolver:id,name']);
        $temp = [];
        // Per item: "before" = the file showing the issue (from the UAT),
        // "after" = the revision proof. The result report shows "before";
        // the revision report shows both side by side. Only images are
        // drawn (PDFs are named in the report).
        $photos = [];
        foreach ($uat->locations as $location) {
            foreach ($location->items as $item) {
                $files = ['before' => [$item->evidence_path, $item->evidence_kind]];
                if ($kind === 'revision') {
                    $files['after'] = [$item->photo_path, $item->photo_kind];
                }
                foreach ($files as $slot => [$path, $fileKind]) {
                    if ($path && $fileKind !== 'file' && ($image = $this->photo($path, $item->id))) {
                        $photos[$item->id][$slot] = $image;
                    }
                }
            }
        }

        $pdf = Pdf::loadView('pdf.uat-report', [
            'uat' => $uat,
            'project' => $uat->project,
            'photos' => $photos,
            'kind' => $kind,
            'generatedAt' => Carbon::now('Asia/Jakarta'),
        ])->setPaper('a4');

        return [ReportPageFooter::apply($pdf, self::title($uat, $kind).' · '.$uat->project->name), $temp];
    }

    public static function title(ProjectUatSession $uat, string $kind): string
    {
        return ($kind === 'revision' ? 'Laporan Hasil Revisi UAT ke-' : 'Laporan Hasil UAT ke-').$uat->round;
    }

    public static function filename(ProjectUatSession $uat, string $kind = 'result'): string
    {
        return ($kind === 'revision' ? 'Laporan-Revisi-UAT-' : 'Laporan-UAT-').$uat->round.'-'.str_replace([' ', '/'], '-', $uat->project->name).'.pdf';
    }

    /**
     * The item's photo, fetched small, as a data URI: dompdf only reads
     * local files inside its chroot (the project folder), so a temp file
     * would show as a broken image.
     */
    private function photo(string $publicId, int $itemId): ?string
    {
        $url = CloudinaryUrl::image($publicId);
        if (! $url) {
            return null;
        }
        $url = str_replace('/image/upload/', '/image/upload/'.self::PHOTO_TRANSFORM.'/', $url);
        try {
            $response = Http::timeout(10)->get($url);
            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! $response->successful() || ! in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                return null;
            }

            return 'data:'.$type.';base64,'.base64_encode($response->body());
        } catch (\Throwable $e) {
            Log::warning('UAT report: photo not fetched', ['item' => $itemId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
