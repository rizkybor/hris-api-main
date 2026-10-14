<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\OperationalCostStatus;
use Illuminate\Validation\Rule;

class InfrastructureToolUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tech_stack_component' => ['sometimes', 'required', 'string', 'max:255'],
            'vendor' => ['sometimes','required', 'string', 'max:255'],
            'monthly_fee' => ['nullable', 'required', 'numeric', 'min:0'],
            'annual_fee' => ['nullable', 'required', 'numeric', 'min:0'],
            // Required while the tool is in use; optional once it isn't.
            'expired_date' => ['nullable', 'date', 'required_if:status,active'],
            'status' => ['sometimes', 'required', Rule::in(OperationalCostStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'tech_stack_component' => 'Tech Stack Component',
            'vendor' => 'Vendor',
            'monthly_fee' => 'Monthly Fee',
            'annual_fee' => 'Annual Fee',
            'expired_date' => 'Expired Date',
            'status' => 'Status',
        ];
    }

    public function messages(): array
    {
        return [
            'expired_date.required_if' => 'Expired wajib diisi untuk item yang Digunakan.',
        ];
    }
}
