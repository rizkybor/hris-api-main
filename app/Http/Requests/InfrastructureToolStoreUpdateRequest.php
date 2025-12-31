<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InfrastructureToolStoreUpdateRequest extends FormRequest
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
            'monthly_fee' => ['sometimes','required', 'numeric', 'min:0'],
            'annual_fee' => ['sometimes','required', 'numeric', 'min:0'],
            'expired_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
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
}

