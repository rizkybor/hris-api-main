<?php

namespace App\Http\Requests;

use App\Enums\OperationalCostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SdmResourceStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sdm_field_id' => ['sometimes', 'required', 'integer', 'exists:sdm_fields,id'],
            'productive_hours_per_month' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sdm_component' => ['nullable', 'string', 'max:255'],
            'capacity_target' => ['nullable', 'string', 'max:255'],
            'actual' => ['nullable', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(OperationalCostStatus::values())],
            // Required while the item is in use; optional once it isn't.
            'expired_date' => ['nullable', 'date', 'required_if:status,active'],
        ];
    }

    public function attributes()
    {
        return [
            'status' => 'Status',
            'expired_date' => 'Expired',
            'sdm_field_id' => 'Bidang',
            'productive_hours_per_month' => 'Jam Produktif / Bulan',
            'sdm_component' => 'SDM Component',
            'capacity_target' => 'Capacity Target',
            'actual' => 'Actual',
        ];
    }

    public function messages(): array
    {
        return [
            'expired_date.required_if' => 'Expired wajib diisi untuk item yang Digunakan.',
        ];
    }
}
