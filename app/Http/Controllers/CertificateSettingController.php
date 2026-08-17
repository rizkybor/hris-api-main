<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\CertificateSettingRequest;
use App\Http\Resources\CertificateSettingResource;
use App\Services\CertificateService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class CertificateSettingController extends Controller implements HasMiddleware
{
    public function __construct(protected CertificateService $certificateService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['certificate-menu']), only: ['show']),
            new Middleware(PermissionMiddleware::using(['certificate-create']), only: ['update']),
        ];
    }

    public function show()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'Certificate Setting Retrieved Successfully', new CertificateSettingResource($this->certificateService->currentSettings()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(CertificateSettingRequest $request)
    {
        $validated = $request->validated();

        try {
            $setting = $this->certificateService->currentSettings();
            $setting->update($validated);

            return ResponseHelper::jsonResponse(true, 'Certificate Setting Updated Successfully', new CertificateSettingResource($setting->fresh()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
