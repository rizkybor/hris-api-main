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
            'scenario' => ['required', 'string', 'in:feature,build'],

            'items' => ['required', 'array', 'min:1'],
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
        ];
    }
}
