<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'selected_fixed_cost_ids' => ['nullable', 'array'],
            'selected_fixed_cost_ids.*' => ['integer', Rule::exists('fixed_costs', 'id')->whereNull('deleted_at')],
            'selected_sdm_resource_ids' => ['nullable', 'array'],
            'selected_sdm_resource_ids.*' => ['integer', Rule::exists('sdm_resources', 'id')->whereNull('deleted_at')],
            'selected_infrastructure_tool_ids' => ['nullable', 'array'],
            'selected_infrastructure_tool_ids.*' => ['integer', Rule::exists('infrastructure_tools', 'id')->whereNull('deleted_at')],
            'margin_multiplier' => ['required', 'numeric', 'min:1'],
            'pm_overhead_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_infra_setup_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'selected_fixed_cost_ids' => 'Fixed Cost items',
            'selected_sdm_resource_ids' => 'SDM Resource items',
            'selected_infrastructure_tool_ids' => 'Infrastructure Tool items',
            'margin_multiplier' => 'Multiplier Margin Jual',
            'pm_overhead_percent' => 'PM Overhead',
            'default_infra_setup_cost' => 'Biaya Setup Infrastruktur Default',
        ];
    }
}
