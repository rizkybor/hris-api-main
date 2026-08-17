<?php

namespace App\Http\Resources;

use App\Http\Resources\SdmResource as SdmResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'selected_fixed_cost_ids' => $this->selected_fixed_cost_ids ?? [],
            'selected_sdm_resource_ids' => $this->selected_sdm_resource_ids ?? [],
            'selected_infrastructure_tool_ids' => $this->selected_infrastructure_tool_ids ?? [],
            'margin_multiplier' => (float) $this->margin_multiplier,
            'pm_overhead_percent' => (float) $this->pm_overhead_percent,
            'default_infra_setup_cost' => (float) $this->default_infra_setup_cost,

            'team_monthly_cost' => $this->team_monthly_cost,
            'total_productive_hours_per_month' => $this->total_productive_hours_per_month,
            'team_size' => $this->team_size,
            'rate_cost_per_hour' => $this->rate_cost_per_hour,
            'rate_sell_per_hour' => $this->rate_sell_per_hour,

            'selected_fixed_costs' => FixedCostResource::collection($this->selectedFixedCosts()),
            'selected_sdm_resources' => SdmResourceResource::collection($this->selectedSdmResources()),
            'selected_infrastructure_tools' => InfrastructureToolResource::collection($this->selectedInfrastructureTools()),

            'updated_at' => $this->updated_at,
        ];
    }
}
