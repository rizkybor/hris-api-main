<?php

namespace App\Services\Pdf;

use App\Models\ProjectUatItem;
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
        $photos = [];
        foreach ($uat->locations as $location) {
            foreach ($location->items as $item) {
                if ($item->photo_path && ($path = $this->photo($item, $temp))) {
                    $photos[$item->id] = $path;
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

    /** A small local copy of the item's photo, or null when it can't be fetched. */
    private function photo(ProjectUatItem $item, array &$temp): ?string
    {
        $url = CloudinaryUrl::image($item->photo_path);
        if (! $url) {
            return null;
        }
        $url = str_replace('/image/upload/', '/image/upload/'.self::PHOTO_TRANSFORM.'/', $url);
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                return null;
            }
            $path = tempnam(sys_get_temp_dir(), 'uat-photo-').'.jpg';
            file_put_contents($path, $response->body());
            $temp[] = $path;

            return $path;
        } catch (\Throwable $e) {
            Log::warning('UAT report: proof photo not fetched', ['item' => $item->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
