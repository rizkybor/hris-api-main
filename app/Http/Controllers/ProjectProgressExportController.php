<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Middleware\EnsureProjectMembership;
use App\Models\Project;
use App\Services\CoverLetterService;
use App\Services\Pdf\ProjectProgressReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Project progress report with an optional cover letter (Surat Pengantar,
 * letter code SPG) in front, as one PDF. The cover letter is a real,
 * numbered Letter linked to the project: a new one is created (needs
 * letter-create) or one written for this project before is reused (needs
 * letter access).
 */
class ProjectProgressExportController extends Controller implements HasMiddleware
{
    public function __construct(
        private ProjectProgressReportService $reports,
        private CoverLetterService $covers,
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-export'])),
            new Middleware(EnsureProjectMembership::class),
        ];
    }

    /**
     * What the export dialog needs: earlier cover letters of this project,
     * the division codes and a pre-filled draft.
     */
    public function options(Request $request, string $id)
    {
        $project = $this->reports->load($id);

        return ResponseHelper::jsonResponse(true, 'Cover Letter Options Retrieved Successfully', $this->covers->options($project, $request->user(), [
            'subject' => 'Laporan Progres Proyek '.$project->name,
            'body' => $this->body($project, $this->reports->summary($project), Carbon::today('Asia/Jakarta')),
        ]), 200);
    }

    /**
     * cover = none | new | existing. Streams the PDF; a new cover letter's
     * number comes back in the X-Letter-Number header.
     */
    public function export(Request $request, string $id)
    {
        $project = $this->reports->load($id);
        $data = $request->validate($this->covers->rules());
        $letter = $this->covers->resolve($data, $project, $request->user(), '1 (satu) berkas Laporan Progres Proyek');

        return $this->covers->respond(
            $this->reports->make($project)->output(),
            $letter,
            ProjectProgressReportService::filename($project),
            'Laporan Progres '.$project->name
        );
    }

    /** Indonesian cover letter text, filled from the project's numbers. */
    private function body(Project $project, array $summary, Carbon $today): string
    {
        $fmt = fn (?Carbon $date) => $date ? $date->locale('id')->translatedFormat('d F Y') : '-';
        $phases = $this->reports->phases($project);
        $uat = $this->reports->uat($project);

        $stage = match (true) {
            $project->status === 'completed' => 'Selesai'.($project->completed_at ? ' ('.$fmt($project->completed_at->copy()->tz('Asia/Jakarta')).')' : ''),
            $phases['retention']['state'] === 'Running' => 'Masa retensi, hingga '.$fmt($phases['retention']['end']),
            $phases['development']['state'] === 'Running' => 'Pengembangan (development), hingga '.$fmt($phases['development']['end']),
            $phases['development']['state'] === 'Upcoming' => 'Belum dimulai',
            default => 'Pengembangan selesai',
        };

        // A short summary table (Uraian / Keterangan) keeps the letter
        // scannable; the full detail is in the attached report.
        $rows = [
            'Nama proyek' => e($project->name),
            'Progres pekerjaan' => $summary['progress'].'% ('.$summary['doneCount'].' dari '.$summary['totalTasks'].' pekerjaan selesai)',
            'Tahap saat ini' => e($stage),
            'Periode development' => $phases['development']['start'] ? $fmt($phases['development']['start']).' s.d. '.$fmt($phases['development']['end']) : '-',
        ];
        if ($phases['retention']['start']) {
            $rows['Masa retensi'] = $fmt($phases['retention']['start']).' s.d. '.$fmt($phases['retention']['end']);
        }
        if ($uat['planned'] || $uat['done']) {
            $rows['UAT'] = $uat['done'].($uat['planned'] ? ' dari '.$uat['planned'] : '').' sesi terlaksana'.($uat['passedRound'] ? ', dinyatakan lulus pada UAT ke-'.$uat['passedRound'] : '');
        }
        $table = CoverLetterService::table($rows);

        return '<p>Dengan hormat,</p>'
            .'<p>Bersama surat ini, kami sampaikan Laporan Progres Proyek <strong>'.e($project->name).'</strong> per tanggal '.$fmt($today).', dengan ringkasan sebagai berikut:</p>'
            .$table
            .'<p>Rincian linimasa, hasil UAT, dan status setiap pekerjaan dapat Bapak/Ibu lihat pada laporan terlampir.</p>'
            .'<p>Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>';
    }
}
