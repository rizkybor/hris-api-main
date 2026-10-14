<?php

namespace App\Http\Requests;

use App\Enums\OperationalCostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FixedCostStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'financial_items' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'actual' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(OperationalCostStatus::values())],
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
