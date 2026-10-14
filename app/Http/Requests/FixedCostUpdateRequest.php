<?php

namespace App\Http\Requests;

use App\Enums\OperationalCostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FixedCostUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'financial_items' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
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
            'financial_items' => 'Financial Items',
            'description' => 'Description',
            'actual' => 'Actual',
            'notes' => 'Notes',
        ];
    }

    public function messages(): array
    {
        return [
            'expired_date.required_if' => 'Expired wajib diisi untuk item yang Digunakan.',
        ];
    }
}
