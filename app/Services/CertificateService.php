<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\CertificateTemplate;
use App\Models\ProjectTask;
use App\Models\StaffTaskAssignee;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CertificateService
{
    public function __construct(
        protected DocumentNumberService $numberService,
        protected CloudinaryManager $cloudinary
    ) {}

    public function currentSettings(): CertificateSetting
    {
        return CertificateSetting::first() ?? CertificateSetting::create([
            'company_code' => 'JCD',
            'number_format' => CertificateSetting::DEFAULT_FORMAT,
        ]);
    }

    /**
     * Create one certificate record for a single recipient, generating a
     * fresh (non-duplicating) number, then render and persist its PDF.
     */
    public function generateOne(
        array $data,
        array $recipient,
        ?CertificateTemplate $template,
        CertificateSetting $settings,
        int $userId,
        ?string $batchId = null
    ): Certificate {
        // WIB, so the number's year/month (and sequence reset) follow Indonesia.
        $date = $this->numberService->today();

        $numberData = $this->numberService->generateCertificateNumber(
            $settings->company_code,
            $data['category_code'],
            $data['program_code'],
            $date,
            $settings->number_format
        );

        $certificate = Certificate::create([
            'certificate_number' => $numberData['number'],
            'title' => $data['title'],
            'recipient_name' => $recipient['name'],
            'employee_id' => $recipient['employee_id'] ?? null,
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'signatory_name' => $data['signatory_name'],
            'signatory_title' => $data['signatory_title'],
            'use_signature' => $data['use_signature'] ?? false,
            'orientation' => $data['orientation'] ?? 'landscape',
            // The task page only makes sense for a recipient picked from the employees.
            'include_tasks' => ! empty($data['include_tasks']) && ! empty($recipient['employee_id']),
            'use_hologram' => $data['use_hologram'] ?? false,
            'certificate_template_id' => $template?->id,
            'company_code' => strtoupper($settings->company_code),
            'category_code' => strtoupper($data['category_code']),
            'program_code' => strtoupper($data['program_code']),
            'year' => $numberData['year'],
            'month_roman' => $numberData['month_roman'],
            'sequence_number' => $numberData['sequence'],
            'batch_id' => $batchId,
            'created_by' => $userId,
        ]);

        $certificate->pdf_path = $this->renderAndStore($certificate, $template);
        $certificate->saveQuietly();

        return $certificate;
    }

    public function renderAndStore(Certificate $certificate, ?CertificateTemplate $template): string
    {
        $portrait = $certificate->orientation === 'portrait';
        $defaultBackground = public_path($portrait ? 'images/certificate-default-bg-portrait.jpg' : 'images/certificate-default-bg.jpg');

        // dompdf can't fetch remote images itself (enable_remote is off in
        // config/dompdf.php, deliberately -- turning it on would let a PDF
        // render arbitrary remote images/CSS from any URL a request feeds
        // it), so a Cloudinary-hosted template background has to be pulled
        // down to a temp file first, same as it used to just be a local
        // storage path.
        $backgroundImagePath = $template?->background_path
            // A print-sized JPEG cropped to the A4 page (150 dpi, centred
            // like background-size: cover) instead of the original upload,
            // which keeps the certificate PDF small.
            ? $this->downloadToTemp(str_replace('/image/upload/', '/image/upload/'.($portrait ? 'w_1240,h_1754' : 'w_1754,h_1240').',c_fill,q_80,f_jpg/', CloudinaryUrl::image($template->background_path)))
            : $defaultBackground;

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'backgroundImagePath' => $backgroundImagePath,
            'companyName' => 'PT. Jendela Cakra Digital',
            'generatedAt' => now(),
            'portrait' => $portrait,
            // The built-in background has its own left-aligned layout; an
            // uploaded one keeps the centred card.
            'modernLayout' => $backgroundImagePath === $defaultBackground,
            'tasks' => $certificate->include_tasks && $certificate->employee_id
                ? $this->completedTasks($certificate->employee_id, $certificate->start_date, $certificate->end_date)
                : null,
        ])->setPaper('a4', $portrait ? 'portrait' : 'landscape');

        $publicId = $this->cloudinary->uploadFromString(
            $pdf->output(),
            CloudinaryFolders::companyFiles('certificates'),
            CloudinaryFolders::filename(str_replace('/', '-', $certificate->certificate_number).'-'.$certificate->id),
            'pdf'
        );

        if ($backgroundImagePath !== $defaultBackground) {
            @unlink($backgroundImagePath);
        }

        return $publicId;
    }

    /**
     * Raw PDF bytes for a generated certificate, fetched from Cloudinary --
     * used by the download/zip-export flows so the response still comes
     * from our own API (matching the frontend's existing blob-download
     * contract) rather than redirecting the browser to Cloudinary.
     */
    public function downloadBytes(Certificate $certificate): string
    {
        $url = CloudinaryUrl::raw($certificate->pdf_path);

        return Http::get($url)->throw()->body();
    }

    /**
     * The employee's finished tasks (staff tasks they marked done and project
     * tasks in Done), newest first, within the certificate period when it has
     * one. Project tasks have no completion timestamp, so their last update
     * stands in for it.
     */
    public function completedTasks(int $employeeId, $start = null, $end = null, int $limit = 200): array
    {
        // The period's days are WIB days; timestamps are stored in UTC.
        $day = fn ($date) => Carbon::parse(Carbon::parse($date)->toDateString(), 'Asia/Jakarta');
        $from = $start ? $day($start)->startOfDay()->utc() : null;
        $to = $end ? $day($end)->endOfDay()->utc() : null;
        $inPeriod = function ($query, string $column) use ($from, $to) {
            $query->when($from, fn ($q) => $q->where($column, '>=', $from))
                ->when($to, fn ($q) => $q->where($column, '<=', $to));
        };

        $staff = StaffTaskAssignee::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'done')
            ->whereNotNull('status_updated_at')
            ->whereHas('task')
            ->tap(fn ($q) => $inPeriod($q, 'status_updated_at'))
            ->with('task:id,title')
            ->latest('status_updated_at')
            ->limit($limit)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->task->title,
                'context' => 'Staff Task',
                'completed_at' => $a->status_updated_at,
            ]);

        $project = ProjectTask::query()
            ->where(fn ($q) => $q->whereIn('id', ProjectTask::assigneeTaskIds($employeeId))->orWhere('assignee_id', $employeeId))
            ->where('status', TaskStatus::DONE->value)
            ->tap(fn ($q) => $inPeriod($q, 'updated_at'))
            ->with('project:id,name')
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'project_id', 'updated_at'])
            ->map(fn ($t) => [
                'title' => $t->name,
                'context' => $t->project?->name ?? 'Project',
                'completed_at' => $t->updated_at,
            ]);

        return $staff->concat($project)
            ->sortByDesc(fn ($t) => $t['completed_at']->getTimestamp())
            ->take($limit)
            ->values()
            ->all();
    }

    private function downloadToTemp(string $url): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cert-bg-');
        file_put_contents($tempPath, Http::get($url)->throw()->body());

        return $tempPath;
    }
}
