<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use App\Services\LicenseCertificateService;
use App\Services\LicenseEventLogger;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * The License Certificate PDF of one license (download).
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
            new Middleware(PermissionMiddleware::using(['license-manager-view']), only: ['download']),
        ];
    }

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
}
