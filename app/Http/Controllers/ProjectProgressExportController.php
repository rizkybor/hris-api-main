<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Middleware\EnsureProjectMembership;
use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Models\Project;
use App\Services\DocumentNumberService;
use App\Services\Pdf\LetterPdfService;
use App\Services\Pdf\PdfMerger;
use App\Services\Pdf\ProjectProgressReportService;
use App\Support\SignatureImage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    public const COVER_CODE = 'SPG';

    public function __construct(
        private ProjectProgressReportService $reports,
        private LetterPdfService $letters,
        private PdfMerger $merger,
        private DocumentNumberService $numbers,
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
        $user = $request->user();
        $canCreate = $user->can('letter-create');
        $canReuse = $canCreate || $user->can('letter-list') || $user->can('letter-menu');

        $previous = $canReuse ? $this->coverLetters($project)->get() : collect();
        $last = $previous->first()
            ?? Letter::query()->whereNotNull('signatory_name')->latest('id')->first();
        $summary = $this->reports->summary($project);
        $today = Carbon::today('Asia/Jakarta');

        return ResponseHelper::jsonResponse(true, 'Cover Letter Options Retrieved Successfully', [
            'can_create' => $canCreate,
            'can_reuse' => $canReuse,
            'letters' => $previous->map(fn (Letter $letter) => [
                'id' => $letter->id,
                'letter_number' => $letter->letter_number,
                'date' => $letter->date?->toDateString(),
                'subject' => $letter->subject,
                'template' => $letter->template ?: 'primary',
            ])->values(),
            // People with a signature (+ company stamp) on file.
            'signatories' => $canCreate ? SignatureImage::signatories() : [],
            'divisions' => $canCreate ? DivisionCode::query()->orderBy('code')->get(['id', 'code', 'name']) : [],
            'draft' => [
                'date' => $today->toDateString(),
                'template' => $previous->first()?->template ?: 'primary',
                'division_code_id' => $previous->first()?->division_code_id,
                'recipient' => $this->recipient($project),
                'subject' => 'Laporan Progres Proyek '.$project->name,
                'body' => $this->body($project, $summary, $today),
                'signatory_name' => $last?->signatory_name,
                'signatory_title' => $last?->signatory_title,
                'use_signature' => (bool) ($last?->use_signature ?? false),
            ],
        ], 200);
    }

    /**
     * cover = none | new | existing. Streams the PDF; a new cover letter's
     * number comes back in the X-Letter-Number header.
     */
    public function export(Request $request, string $id)
    {
        $project = $this->reports->load($id);
        $data = $request->validate([
            'cover' => ['required', Rule::in(['none', 'new', 'existing'])],
            'letter_id' => ['required_if:cover,existing', 'nullable', 'integer'],
            'template' => ['required_if:cover,new', 'nullable', Rule::in(['primary', 'secondary'])],
            'division_code_id' => ['required_if:cover,new', 'nullable', 'integer', 'exists:division_codes,id'],
            'date' => ['required_if:cover,new', 'nullable', 'date_format:Y-m-d'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'subject' => ['required_if:cover,new', 'nullable', 'string', 'max:255'],
            'body' => ['required_if:cover,new', 'nullable', 'string', 'max:20000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'use_signature' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();

        $letter = null;
        if ($data['cover'] === 'new') {
            if (! $user->can('letter-create')) {
                return ResponseHelper::jsonResponse(false, 'You do not have permission to create letters.', null, 403);
            }
            // A signed letter needs someone whose signature is on file.
            if (! empty($data['use_signature']) && ! SignatureImage::pathFor($data['signatory_name'] ?? null)) {
                throw ValidationException::withMessages(['signatory_name' => 'Pick a signatory whose signature is on file.']);
            }
            $letter = $this->createCoverLetter($project, $data, $user->id);
        } elseif ($data['cover'] === 'existing') {
            if (! ($user->can('letter-create') || $user->can('letter-list') || $user->can('letter-menu'))) {
                return ResponseHelper::jsonResponse(false, 'You do not have permission to view letters.', null, 403);
            }
            $letter = $this->coverLetters($project)->find($data['letter_id']);
            if (! $letter) {
                return ResponseHelper::jsonResponse(false, 'Cover letter not found for this project.', null, 404);
            }
        }

        $report = $this->reports->make($project)->output();
        $filename = ProjectProgressReportService::filename($project);
        $headers = ['Content-Type' => 'application/pdf'];

        if ($letter) {
            $bytes = $this->merger->merge([$this->letters->make($letter)->output(), $report], 'Laporan Progres '.$project->name);
            $headers['X-Letter-Number'] = $letter->letter_number;
        } else {
            $bytes = $report;
        }
        $headers['Content-Disposition'] = 'inline; filename="'.$filename.'"';

        return response($bytes, 200, $headers);
    }

    private function coverLetters(Project $project)
    {
        return Letter::query()
            ->where('project_id', $project->id)
            ->where('status', 'issued')
            ->whereHas('letterCode', fn ($query) => $query->where('code', self::COVER_CODE))
            ->with(['letterCode', 'divisionCode'])
            ->latest('date')
            ->latest('id');
    }

    private function createCoverLetter(Project $project, array $data, int $userId): Letter
    {
        $code = LetterCode::query()->firstOrCreate(['code' => self::COVER_CODE], ['name' => 'Surat Pengantar']);
        $division = DivisionCode::findOrFail($data['division_code_id']);
        $date = Carbon::parse($data['date']);

        return DB::transaction(function () use ($project, $data, $userId, $code, $division, $date) {
            $generated = $this->numbers->generateLetterNumber($code->code, 'E', $division->code, $date);

            return Letter::create([
                'letter_number' => $generated['number'],
                'sequence' => $generated['sequence'],
                'year' => $generated['year'],
                'letter_code_id' => $code->id,
                'division_code_id' => $division->id,
                'type' => 'E',
                'date' => $date->toDateString(),
                'subject' => $data['subject'],
                'recipient' => $data['recipient'] ?? null,
                'body' => $data['body'],
                'signatory_name' => $data['signatory_name'] ?? null,
                'signatory_title' => $data['signatory_title'] ?? null,
                'use_signature' => (bool) ($data['use_signature'] ?? false),
                'template' => $data['template'],
                'project_id' => $project->id,
                'created_by' => $userId,
            ])->load(['letterCode', 'divisionCode']);
        });
    }

    private function recipient(Project $project): string
    {
        $client = $project->client;
        if (! $client) {
            return '';
        }

        return trim(($client->pic_name ? 'Bapak/Ibu '.$client->pic_name."\n" : '').$client->name);
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
        $table = '<table><tbody><tr><th style="width: 34%;">Uraian</th><th>Keterangan</th></tr>';
        foreach ($rows as $label => $value) {
            $table .= '<tr><td>'.$label.'</td><td>'.$value.'</td></tr>';
        }
        $table .= '</tbody></table>';

        return '<p>Dengan hormat,</p>'
            .'<p>Bersama surat ini, kami sampaikan Laporan Progres Proyek <strong>'.e($project->name).'</strong> per tanggal '.$fmt($today).', dengan ringkasan sebagai berikut:</p>'
            .$table
            .'<p>Rincian linimasa, hasil UAT, dan status setiap pekerjaan dapat Bapak/Ibu lihat pada laporan terlampir.</p>'
            .'<p>Demikian surat pengantar ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>';
    }
}
