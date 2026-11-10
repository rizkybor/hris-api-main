<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Mail\LicenseCertificateMail;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use App\Services\LicenseCertificateService;
use App\Services\LicenseEventLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * The License Certificate PDF of one license: download, send by email
 * (attached), or a 7-day signed link to send over WhatsApp, which opens
 * without logging in (publicShow).
 */
class LicenseCertificateController extends Controller implements HasMiddleware
{
    public function __construct(
        private LicenseCertificateService $certificates,
        private LicenseEventLogger $eventLogger
    ) {}

    public static function middleware()
    {
        return [
            // Sharing hands out the same PDF as a download.
            new Middleware(PermissionMiddleware::using(['license-manager-view']), only: ['download', 'shareByEmail', 'shareLink']),
        ];
    }

    /** Days a shared certificate link stays valid. */
    public const LINK_DAYS = 7;

    public function download(string $product, string $license)
    {
        $productModel = LicenseProduct::findByCode($product);
        $model = $productModel ? License::forProduct($productModel)->with('product')->find($license) : null;
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }

        try {
            $pdf = $this->certificates->render($model);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }

        $this->eventLogger->log($model, LicenseEvent::CERTIFICATE_DOWNLOADED, ['meta' => [
            'reference' => LicenseCertificateService::reference($model),
            'terms_version' => $model->terms_version,
        ]]);

        $filename = $this->certificates->filename($model);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store',
            // Lets the browser read the filename from a blob download.
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    /**
     * Emails the certificate to the licensee (or whoever is given), PDF
     * attached. Sent right away so the admin sees a failure immediately.
     */
    public function shareByEmail(Request $request, string $product, string $license)
    {
        $data = $request->validate([
            'to' => ['required', 'email:rfc', 'max:255'],
            'cc' => ['nullable', 'array', 'max:5'],
            'cc.*' => ['email:rfc', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [], ['to' => 'Email', 'cc.*' => 'CC']);

        $model = $this->find($product, $license);
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }

        try {
            $keys = $this->certificates->bundleKeys($model);
            Mail::to($data['to'])
                ->cc($data['cc'] ?? [])
                ->send(new LicenseCertificateMail(
                    $model,
                    $keys,
                    $this->certificates->render($model),
                    $this->certificates->filename($model),
                    $data['recipient_name'] ?? null,
                    $data['message'] ?? null,
                ));
        } catch (\Throwable $e) {
            report($e);

            return ResponseHelper::jsonResponse(false, 'The email could not be sent. Please check the mail settings and try again.', null, 500);
        }

        $this->eventLogger->log($model, LicenseEvent::CERTIFICATE_EMAILED, ['meta' => array_filter([
            'to' => $data['to'],
            'cc' => $data['cc'] ?? null,
            'reference' => LicenseCertificateService::certificateReference($keys),
        ])]);

        return ResponseHelper::jsonResponse(true, 'Certificate Sent Successfully', ['to' => $data['to']], 200);
    }

    /**
     * A signed, 7-day link to the certificate PDF for a WhatsApp message.
     * Nothing is stored: the signature (APP_KEY) is the only key, and a
     * changed or expired link is refused.
     */
    public function shareLink(Request $request, string $product, string $license)
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30']]);

        $model = $this->find($product, $license);
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }

        $expiresAt = now()->addDays(self::LINK_DAYS);
        $url = URL::temporarySignedRoute('licenses.certificate.public', $expiresAt, ['license' => $model->id]);

        $this->eventLogger->log($model, LicenseEvent::CERTIFICATE_SHARED_WHATSAPP, ['meta' => array_filter([
            'phone' => $data['phone'] ?? null,
            'expires_at' => $expiresAt->utc()->toIso8601ZuluString(),
        ])]);

        return ResponseHelper::jsonResponse(true, 'Share Link Ready', [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 200);
    }

    /**
     * Public: the certificate from a shared link (no login). A bad or
     * expired signature gets the friendly "link unavailable" page.
     */
    public function publicShow(Request $request, int $license)
    {
        if (! $request->hasValidSignature()) {
            return response()->view('licenses.link-unavailable', [], 403);
        }

        $model = License::with('product')->find($license);
        if (! $model) {
            return response()->view('licenses.link-unavailable', [], 404);
        }

        $pdf = $this->certificates->render($model);
        $this->eventLogger->log($model, LicenseEvent::CERTIFICATE_LINK_OPENED, ['meta' => [
            'ip' => $request->ip(),
        ]]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->certificates->filename($model).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function find(string $product, string $license): ?License
    {
        $productModel = LicenseProduct::findByCode($product);

        return $productModel ? License::forProduct($productModel)->with('product')->find($license) : null;
    }
}
