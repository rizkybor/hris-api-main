<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\CertificateTemplate;
use App\Services\Cloudinary\CloudinaryFolders;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\Cloudinary\CloudinaryUrl;
use Barryvdh\DomPDF\Facade\Pdf;
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
        string $recipientName,
        ?CertificateTemplate $template,
        CertificateSetting $settings,
        int $userId,
        ?string $batchId = null
    ): Certificate {
        $date = now();

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
            'recipient_name' => $recipientName,
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'signatory_name' => $data['signatory_name'],
            'signatory_title' => $data['signatory_title'],
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
        $defaultBackground = public_path('images/certificate-default-bg.png');

        // dompdf can't fetch remote images itself (enable_remote is off in
        // config/dompdf.php, deliberately -- turning it on would let a PDF
        // render arbitrary remote images/CSS from any URL a request feeds
        // it), so a Cloudinary-hosted template background has to be pulled
        // down to a temp file first, same as it used to just be a local
        // storage path.
        $backgroundImagePath = $template?->background_path
            ? $this->downloadToTemp(CloudinaryUrl::image($template->background_path))
            : $defaultBackground;

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'backgroundImagePath' => $backgroundImagePath,
            'companyName' => 'PT. Jendela Cakra Digital',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

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

    private function downloadToTemp(string $url): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cert-bg-');
        file_put_contents($tempPath, Http::get($url)->throw()->body());

        return $tempPath;
    }
}
