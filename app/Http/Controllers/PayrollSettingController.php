<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PayrollSettingResource;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-create']), only: ['show']),
            new Middleware(PermissionMiddleware::using(['payroll-setting-edit']), only: ['update']),
        ];
    }

    public function show()
    {
        try {
            $setting = PayrollSetting::current()->load('updater');

            return ResponseHelper::jsonResponse(true, 'Payroll Setting Retrieved Successfully', new PayrollSettingResource($setting), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'attendance_exempt_roles_enabled' => ['required', 'boolean'],
        ]);

        try {
            $setting = PayrollSetting::current();
            $setting->update([
                ...$validated,
                'updated_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Payroll Setting Updated Successfully', new PayrollSettingResource($setting->fresh('updater')), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
