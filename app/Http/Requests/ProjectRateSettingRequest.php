<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'team_monthly_cost' => ['required', 'numeric', 'min:0'],
            'productive_hours_per_person' => ['required', 'numeric', 'min:1'],
            'team_size' => ['required', 'integer', 'min:1'],
            'margin_multiplier' => ['required', 'numeric', 'min:1'],
            'pm_overhead_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_infra_setup_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'team_monthly_cost' => 'Total Biaya Operasional Tim / Bulan',
            'productive_hours_per_person' => 'Jam Produktif per Orang / Bulan',
            'team_size' => 'Jumlah Orang di Tim',
            'margin_multiplier' => 'Multiplier Margin Jual',
            'pm_overhead_percent' => 'PM Overhead',
            'default_infra_setup_cost' => 'Biaya Setup Infrastruktur Default',
        ];
    }
}
