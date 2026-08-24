<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'faktur_pajak_number' => ['nullable', 'string', 'max:50'],
            'project_id' => ['nullable', 'exists:projects,id'],
            // No "/" allowed: this is a short code slotted into the
            // auto-generated invoice number (INV/JCD-{client_code}/DDMM/
            // YY.NNN) -- a value that already contains "/" (e.g. someone
            // pasting a full invoice number in here instead of just the
            // code) doubles up and breaks the generated number.
            'client_code' => ['required', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_pic' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_npwp' => ['nullable', 'string', 'max:25'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
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

    public function messages(): array
    {
        return [
            'client_code.regex' => 'Client Code should be a short code (e.g. "ZACO"), not a full invoice number -- it gets combined with the date and sequence to build the invoice number automatically.',
        ];
    }
}
