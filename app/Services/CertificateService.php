<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateSetting;
use App\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    public function __construct(protected DocumentNumberService $numberService) {}

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
        $backgroundImagePath = $template?->background_path
            ? Storage::disk('public')->path($template->background_path)
            : public_path('images/certificate-default-bg.png');

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'backgroundImagePath' => $backgroundImagePath,
            'companyName' => 'PT. Jendela Cakra Digital',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $filename = 'certificates/'.str_replace('/', '-', $certificate->certificate_number).'-'.$certificate->id.'.pdf';
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }

    public function absolutePdfPath(Certificate $certificate): string
    {
        return Storage::disk('public')->path($certificate->pdf_path);
    }
}
