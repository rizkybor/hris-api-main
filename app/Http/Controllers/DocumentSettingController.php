<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DocumentSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class DocumentSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['document-setting-edit'])),
        ];
    }

    public function show()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'Document Setting Retrieved Successfully', $this->payload(DocumentSetting::current()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'signatory_show_degree' => ['required', 'boolean'],
        ]);

        try {
            $setting = DocumentSetting::current();
            $setting->update($validated);
            DocumentSetting::forget();

            return ResponseHelper::jsonResponse(true, 'Document Setting Updated Successfully', $this->payload($setting->fresh()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function payload(DocumentSetting $setting): array
    {
        return [
            'signatory_show_degree' => $setting->signatory_show_degree,
        ];
    }
}
