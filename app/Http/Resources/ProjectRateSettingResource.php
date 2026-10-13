<?php

namespace App\Http\Resources;

use App\Http\Resources\SdmResource as SdmResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fixedCosts = $this->selectedFixedCosts();
        $sdmResources = $this->selectedSdmResources();
        $infrastructureTools = $this->selectedInfrastructureTools();

        return [
            'id' => $this->id,
            // Only ids of items that still exist: a soft-deleted item's id
            // would otherwise stay selected in the Rate Setup modal without
            // a checkbox to untick it, and get re-saved on every edit.
            'selected_fixed_cost_ids' => $fixedCosts->pluck('id')->values(),
            'selected_sdm_resource_ids' => $sdmResources->pluck('id')->values(),
            'selected_infrastructure_tool_ids' => $infrastructureTools->pluck('id')->values(),
            'margin_multiplier' => (float) $this->margin_multiplier,
            'pm_overhead_percent' => (float) $this->pm_overhead_percent,
            'default_infra_setup_cost' => (float) $this->default_infra_setup_cost,

            'team_monthly_cost' => $this->team_monthly_cost,
            'total_productive_hours_per_month' => $this->total_productive_hours_per_month,
            'team_size' => $this->team_size,
            'rate_cost_per_hour' => $this->rate_cost_per_hour,
            'rate_sell_per_hour' => $this->rate_sell_per_hour,

            'selected_fixed_costs' => FixedCostResource::collection($fixedCosts),
            'selected_sdm_resources' => SdmResourceResource::collection($sdmResources),
            'selected_infrastructure_tools' => InfrastructureToolResource::collection($infrastructureTools),

            'updated_at' => $this->updated_at,
        ];
    }
}
