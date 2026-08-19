<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CertificateGenerateRequest;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\PaginateResource;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\CertificateService;
use App\Services\Cloudinary\CloudinaryManager;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use ZipArchive;

class CertificateController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CertificateService $certificateService,
        protected DocumentNumberService $numberService,
        protected CloudinaryManager $cloudinary,
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['certificate-menu|certificate-list']), only: ['index', 'show', 'getStatistics', 'previewNumber', 'download']),
            new Middleware(PermissionMiddleware::using(['certificate-create']), only: ['generate']),
            new Middleware(PermissionMiddleware::using(['certificate-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Certificate::with('creator')
                ->when($request->search, fn ($q) => $q->search($request->search))
                ->when($request->type === 'batch', fn ($q) => $q->whereNotNull('batch_id'))
                ->when($request->type === 'individual', fn ($q) => $q->whereNull('batch_id'))
                ->latest();

            $certificates = $query->paginate($request->row_per_page ?? 10);

            return ResponseHelper::jsonResponse(true, 'Certificates Retrieved Successfully', PaginateResource::make($certificates, CertificateResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getStatistics()
    {
        try {
            $stats = Certificate::selectRaw('
                COUNT(*) as total_certificates,
                COUNT(DISTINCT batch_id) as total_batches,
                COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as this_month
            ', [now()->month, now()->year])->first();

            return ResponseHelper::jsonResponse(true, 'Statistics Retrieved Successfully', [
                'total_certificates' => (int) $stats->total_certificates,
                'total_batches' => (int) $stats->total_batches,
                'this_month' => (int) $stats->this_month,
            ], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Read-only preview of what the next certificate number will look like
     * for a given category/program, without consuming the real sequence.
     */
    public function previewNumber(Request $request)
    {
        $validated = $request->validate([
            'category_code' => ['required', 'string', 'max:50'],
            'program_code' => ['required', 'string', 'max:50'],
        ]);

        try {
            $settings = $this->certificateService->currentSettings();

            $preview = $this->numberService->peekCertificateNumber(
                $settings->company_code,
                $validated['category_code'],
                $validated['program_code'],
                now(),
                $settings->number_format
            );

            return ResponseHelper::jsonResponse(true, 'Preview Generated Successfully', $preview, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function generate(CertificateGenerateRequest $request)
    {
        $data = $request->validated();

        try {
            $settings = $this->certificateService->currentSettings();
            $template = $data['certificate_template_id'] ?? null
                ? CertificateTemplate::findOrFail($data['certificate_template_id'])
                : null;

            $recipients = $data['recipients'];
            $isBulk = count($recipients) > 1;
            $batchId = $isBulk ? (string) Str::uuid() : null;

            $certificates = [];
            foreach ($recipients as $recipient) {
                $certificates[] = $this->certificateService->generateOne(
                    $data,
                    $recipient['name'],
                    $template,
                    $settings,
                    Auth::id(),
                    $batchId
                );
            }

            if (! $isBulk) {
                $certificate = $certificates[0];

                return response($this->certificateService->downloadBytes($certificate), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.str_replace('/', '-', $certificate->certificate_number).'.pdf"',
                ]);
            }

            return $this->streamZip($certificates, $batchId);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    private function streamZip(array $certificates, string $batchId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir.'/certificates-'.$batchId.'.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($certificates as $certificate) {
            $zip->addFromString(
                str_replace('/', '-', $certificate->certificate_number).'.pdf',
                $this->certificateService->downloadBytes($certificate)
            );
        }

        $zip->close();

        return response()->download($zipPath, 'certificates-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend(true);
    }

    public function show(int $id)
    {
        try {
            $certificate = Certificate::with(['creator', 'template'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Certificate Retrieved Successfully', new CertificateResource($certificate), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Certificate Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function download(int $id)
    {
        try {
            $certificate = Certificate::findOrFail($id);

            if (! $certificate->pdf_path) {
                return ResponseHelper::jsonResponse(false, 'Certificate File Not Found', null, 404);
            }

            return response($this->certificateService->downloadBytes($certificate), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.str_replace('/', '-', $certificate->certificate_number).'.pdf"',
            ]);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Certificate Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $certificate = Certificate::findOrFail($id);

            $this->cloudinary->delete($certificate->pdf_path, 'raw');

            $certificate->delete();

            return ResponseHelper::jsonResponse(true, 'Certificate Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Certificate Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
