<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectCalculationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'scenario' => $this->scenario,
            'rate_cost_per_hour' => (float) $this->rate_cost_per_hour,
            'rate_sell_per_hour' => (float) $this->rate_sell_per_hour,
            'pm_overhead_percent' => $this->pm_overhead_percent !== null ? (float) $this->pm_overhead_percent : null,
            'infra_setup_cost' => $this->infra_setup_cost !== null ? (float) $this->infra_setup_cost : null,
            'rate_setting_snapshot' => $this->rate_setting_snapshot,
            'items' => $this->items,
            'subtotal' => (float) $this->subtotal,
            'buffer_total' => (float) $this->buffer_total,
            'pm_overhead_total' => (float) $this->pm_overhead_total,
            'margin_percent' => $this->margin_percent !== null ? (float) $this->margin_percent : null,
            'margin_total' => (float) $this->margin_total,
            'grand_total' => (float) $this->grand_total,
            'include_ppn' => (bool) $this->include_ppn,
            'ppn_percent' => (float) $this->ppn_percent,
            'total_with_ppn' => $this->total_with_ppn !== null ? (float) $this->total_with_ppn : null,
            'include_pph' => (bool) $this->include_pph,
            'pph_type' => $this->pph_type,
            'pph_percent' => $this->pph_percent !== null ? (float) $this->pph_percent : null,
            'pph_amount' => $this->pph_amount !== null ? (float) $this->pph_amount : null,
            'net_received' => $this->net_received !== null ? (float) $this->net_received : null,
            'estimated_duration_weeks' => $this->estimated_duration_weeks,
            'notes' => $this->notes,
            'creator' => [
                'id' => $this->whenLoaded('creator', fn () => $this->creator->id),
                'name' => $this->whenLoaded('creator', fn () => $this->creator->name),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
