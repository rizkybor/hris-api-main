<?php

namespace App\Http\Requests;

use App\Enums\PphType;
use Illuminate\Foundation\Http\FormRequest;

class ProjectCalculationRequest extends FormRequest
{
    /**
     * The frontend submits this as multipart/form-data (the app's shared
     * axios instance defaults to it), which stringifies booleans as the
     * literal "true"/"false" -- values Laravel's `boolean` rule rejects
     * (it only accepts 1/0/"1"/"0"/true/false). Normalize before validating.
     */
    protected function prepareForValidation(): void
    {
        foreach (['include_ppn', 'include_pph'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $scenario = $this->input('scenario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'scenario' => ['required', 'string', 'in:feature,build,landing_page'],

            'items' => [$scenario === 'landing_page' ? 'nullable' : 'required', 'array'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.complexity_factor' => ['required', 'numeric', 'min:0.1'],
            'items.*.buffer_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items.*.analysis_hours' => [$scenario === 'feature' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.dev_hours' => [$scenario === 'feature' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.testing_hours' => [$scenario === 'feature' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.deploy_hours' => [$scenario === 'feature' ? 'required' : 'nullable', 'numeric', 'min:0'],

            'items.*.estimated_hours' => [$scenario === 'build' ? 'required' : 'nullable', 'numeric', 'min:0'],

            'pm_overhead_percent' => [$scenario === 'build' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
            'infra_setup_cost' => [$scenario === 'build' ? 'required' : 'nullable', 'numeric', 'min:0'],

            // Landing Page's own fixed-shape fields -- not part of the
            // "items" list, since it's a single package per calculation.
            'server_type' => [$scenario === 'landing_page' ? 'required' : 'nullable', 'string', 'in:dedicated,shared'],
            'design_type' => [$scenario === 'landing_page' ? 'required' : 'nullable', 'string', 'in:dedicated,template'],
            'estimated_hours' => [$scenario === 'landing_page' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'rate_developer' => ['nullable', 'numeric', 'min:0'],
            'developer_count' => ['nullable', 'integer', 'min:1'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],

            'include_ppn' => ['nullable', 'boolean'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'include_pph' => ['nullable', 'boolean'],
            'pph_type' => [$this->boolean('include_pph') ? 'required' : 'nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph_percent' => [$this->boolean('include_pph') ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],

            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama Estimasi',
            'client_name' => 'Nama Klien',
            'scenario' => 'Skenario',
            'items' => 'Daftar Item',
            'server_type' => 'Jenis Server',
            'design_type' => 'Jenis Design',
            'estimated_hours' => 'Estimasi Waktu Pengerjaan',
            'rate_developer' => 'Rate Developer',
            'developer_count' => 'Jumlah Developer',
            'margin_percent' => 'Margin Jual',
        ];
    }
}
