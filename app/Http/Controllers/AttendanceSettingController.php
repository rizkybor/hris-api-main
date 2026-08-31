<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\AttendanceSettingResource;
use App\Models\AttendanceSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AttendanceSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-setting-edit']), only: ['update']),
        ];
    }

    /**
     * Deliberately ungated beyond auth:sanctum -- every employee needs to
     * read this to know whether their own Clock In/Out buttons should be
     * enabled on weekends, not just Superadmin/Manager who may edit it.
     */
    public function show()
    {
        try {
            $setting = AttendanceSetting::current()->load('updater');

            return ResponseHelper::jsonResponse(true, 'Attendance Setting Retrieved Successfully', new AttendanceSettingResource($setting), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'allow_weekend_check_in' => ['required', 'boolean'],
        ]);

        try {
            $setting = AttendanceSetting::current();
            $setting->update([
                'allow_weekend_check_in' => $validated['allow_weekend_check_in'],
                'updated_by' => $request->user()->id,
            ]);

            return ResponseHelper::jsonResponse(true, 'Attendance Setting Updated Successfully', new AttendanceSettingResource($setting->fresh('updater')), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
