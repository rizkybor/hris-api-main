<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProjectRateSettingRequest;
use App\Http\Resources\FixedCostResource;
use App\Http\Resources\InfrastructureToolResource;
use App\Http\Resources\ProjectRateSettingResource;
use App\Http\Resources\SdmResource;
use App\Models\FixedCost;
use App\Models\InfrastructureTool;
use App\Models\SdmResource as SdmResourceModel;
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
            new Middleware(PermissionMiddleware::using(['project-calculator-settings']), only: ['update', 'options']),
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

    /**
     * Every Fixed Cost, SDM Resource and Infrastructure Tool the Team Rate
     * Setup can pick from -- all of them, whatever their Operational Cost
     * usage status (the Operational Cost totals only count "Digunakan" items,
     * but the team rate is set up independently of that).
     */
    public function options()
    {
        try {
            return ResponseHelper::jsonResponse(true, 'Rate Setting Options Retrieved Successfully', [
                'fixed_costs' => FixedCostResource::collection(FixedCost::orderBy('financial_items')->get()),
                'sdm_resources' => SdmResource::collection(SdmResourceModel::with('field')->orderBy('sdm_component')->get()),
                'infrastructure_tools' => InfrastructureToolResource::collection(InfrastructureTool::orderBy('tech_stack_component')->get()),
            ], 200);
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
