<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LandingPageRateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'server_dedicated_price' => ['required', 'numeric', 'min:0'],
            'server_shared_price' => ['required', 'numeric', 'min:0'],
            'design_dedicated_price' => ['required', 'numeric', 'min:0'],
            'design_template_price' => ['required', 'numeric', 'min:0'],
            'default_rate_developer' => ['required', 'numeric', 'min:0'],
            'margin_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'server_dedicated_price' => 'Harga Server Dedicated',
            'server_shared_price' => 'Harga Server Shared',
            'design_dedicated_price' => 'Harga Design Dedicated',
            'design_template_price' => 'Harga Design Template',
            'default_rate_developer' => 'Rate Developer Default',
            'margin_percent' => 'Margin Jual',
        ];
    }
}
