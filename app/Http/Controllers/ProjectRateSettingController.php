<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectRateSettingRequest;
use App\Http\Resources\ProjectRateSettingResource;
use App\Models\ProjectRateSetting;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProjectRateSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['project-calculator-menu']), only: ['show']),
            new Middleware(PermissionMiddleware::using(['project-calculator-settings']), only: ['update']),
        ];
    }

    /**
     * There is only ever one rate setting row (company-wide baseline).
     * Seeded with sensible defaults on first read if it doesn't exist yet.
     */
    private function current(): ProjectRateSetting
    {
        return ProjectRateSetting::first() ?? ProjectRateSetting::create([
            'selected_fixed_cost_ids' => [],
            'selected_sdm_resource_ids' => [],
            'selected_infrastructure_tool_ids' => [],
            'margin_multiplier' => 2.5,
            'pm_overhead_percent' => 12,
            'default_infra_setup_cost' => 2000000,
        ]);
    }

    public function show()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'Rate Setting Retrieved Successfully', new ProjectRateSettingResource($this->current()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(ProjectRateSettingRequest $request)
    {
        $validated = $request->validated();

        try {
            $setting = $this->current();
            $setting->update($validated);

            return ResponseHelper::jsonResponse(true, 'Rate Setting Updated Successfully', new ProjectRateSettingResource($setting->fresh()), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
