<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_monthly_cost' => (float) $this->team_monthly_cost,
            'productive_hours_per_person' => (float) $this->productive_hours_per_person,
            'team_size' => (int) $this->team_size,
            'margin_multiplier' => (float) $this->margin_multiplier,
            'pm_overhead_percent' => (float) $this->pm_overhead_percent,
            'default_infra_setup_cost' => (float) $this->default_infra_setup_cost,
            'rate_cost_per_hour' => $this->rate_cost_per_hour,
            'rate_sell_per_hour' => $this->rate_sell_per_hour,
            'total_productive_hours_per_month' => (float) ($this->productive_hours_per_person * $this->team_size),
            'updated_at' => $this->updated_at,
        ];
    }
}
