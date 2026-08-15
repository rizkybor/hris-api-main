<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['sometimes', 'required', 'string', 'max:20'],
            'client_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_pic' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'date' => ['sometimes', 'required', 'date'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'string', 'max:50'],
            'items.*.rate' => ['nullable', 'string', 'max:100'],
            'items.*.total' => ['required', 'numeric', 'min:0'],
            'ppn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string'],
        ];
    }
}
