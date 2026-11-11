<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Mail\LicenseCertificateMail;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseKey;
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

    public function download(Request $request, string $product, string $license)
    {
        $model = $this->find($product, $license);
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }
        [$key, $denied] = $this->resolveKey($request, $model);
        if ($denied) {
            return $denied;
        }

        try {
            $pdf = $this->certificates->render($model, $key);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }

        $this->eventLogger->log($key?->license ?? $model, LicenseEvent::CERTIFICATE_DOWNLOADED, ['meta' => array_filter([
            'reference' => $key ? LicenseCertificateService::keyReference($key) : LicenseCertificateService::reference($model),
            'terms_version' => $model->terms_version,
        ] + $this->keyMeta($key))]);

        $filename = $this->certificates->filename($model, $key);

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
            'key_id' => ['nullable', 'integer'],
        ], [], ['to' => 'Email', 'cc.*' => 'CC']);

        $model = $this->find($product, $license);
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }
        [$key, $denied] = $this->resolveKey($request, $model);
        if ($denied) {
            return $denied;
        }

        try {
            $keys = $key ? collect([$key->license]) : $this->certificates->bundleKeys($model);
            Mail::to($data['to'])
                ->cc($data['cc'] ?? [])
                ->send(new LicenseCertificateMail(
                    $key?->license ?? $model,
                    $keys,
                    $this->certificates->render($model, $key),
                    $this->certificates->filename($model, $key),
                    $data['recipient_name'] ?? null,
                    $data['message'] ?? null,
                    $key,
                ));
        } catch (\Throwable $e) {
            report($e);

            return ResponseHelper::jsonResponse(false, 'The email could not be sent. Please check the mail settings and try again.', null, 500);
        }

        $this->eventLogger->log($key?->license ?? $model, LicenseEvent::CERTIFICATE_EMAILED, ['meta' => array_filter([
            'to' => $data['to'],
            'cc' => $data['cc'] ?? null,
            'reference' => $key ? LicenseCertificateService::keyReference($key) : LicenseCertificateService::certificateReference($keys),
        ] + $this->keyMeta($key))]);

        return ResponseHelper::jsonResponse(true, 'Certificate Sent Successfully', ['to' => $data['to']], 200);
    }

    /**
     * A signed, 7-day link to the certificate PDF for a WhatsApp message.
     * Nothing is stored: the signature (APP_KEY) is the only key, and a
     * changed or expired link is refused.
     */
    public function shareLink(Request $request, string $product, string $license)
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30'], 'key_id' => ['nullable', 'integer']]);

        $model = $this->find($product, $license);
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'License or Product Not Found', null, 404);
        }
        [$key, $denied] = $this->resolveKey($request, $model);
        if ($denied) {
            return $denied;
        }

        $expiresAt = now()->addDays(self::LINK_DAYS);
        // The key is part of the signed URL, so it can't be changed.
        $url = URL::temporarySignedRoute('licenses.certificate.public', $expiresAt, array_filter([
            'license' => $model->id,
            'key' => $key?->id,
        ]));

        $this->eventLogger->log($key?->license ?? $model, LicenseEvent::CERTIFICATE_SHARED_WHATSAPP, ['meta' => array_filter([
            'phone' => $data['phone'] ?? null,
            'expires_at' => $expiresAt->utc()->toIso8601ZuluString(),
        ] + $this->keyMeta($key))]);

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

        $key = null;
        if ($request->query('key')) {
            $key = $this->certificates->findKey($model, $request->query('key'));
            // The key was removed (Max devices lowered) or its license deleted.
            if (! $key) {
                return response()->view('licenses.link-unavailable', [], 404);
            }
        }

        $pdf = $this->certificates->render($model, $key);
        $this->eventLogger->log($key?->license ?? $model, LicenseEvent::CERTIFICATE_LINK_OPENED, ['meta' => [
            'ip' => $request->ip(),
        ] + $this->keyMeta($key)]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->certificates->filename($model, $key).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * The optional key_id (one key of a "one key per device" license or of
     * its bundle): [key or null, error response or null]. A used key is
     * dead, so it gets no certificate.
     *
     * @return array{0: ?LicenseKey, 1: mixed}
     */
    private function resolveKey(Request $request, License $license): array
    {
        $keyId = $request->input('key_id');
        if (! $keyId) {
            return [null, null];
        }

        $key = $this->certificates->findKey($license, $keyId);
        if (! $key) {
            return [null, ResponseHelper::jsonResponse(false, 'Key Not Found', null, 404)];
        }
        if ($key->isUsed()) {
            return [null, ResponseHelper::jsonResponse(false, 'This key has been used and can no longer be activated.', null, 422)];
        }
        $key->load('license.product');

        return [$key, null];
    }

    /** Event meta for a one-key certificate. */
    private function keyMeta(?LicenseKey $key): array
    {
        return $key ? ['key' => $key->key, 'key_position' => $key->position] : [];
    }

    private function find(string $product, string $license): ?License
    {
        $productModel = LicenseProduct::findByCode($product);

        return $productModel ? License::forProduct($productModel)->with('product')->find($license) : null;
    }
}
