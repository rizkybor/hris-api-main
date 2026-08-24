<?php

namespace App\Http\Requests;

use App\Enums\PphType;
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
            'faktur_pajak_number' => ['nullable', 'string', 'max:50'],
            'project_id' => ['nullable', 'exists:projects,id'],
            // See InvoiceStoreRequest for why "/" is disallowed here.
            'client_code' => ['sometimes', 'required', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'client_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_pic' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_npwp' => ['nullable', 'string', 'max:25'],
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
            'pph23_type' => ['nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph23_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_code.regex' => 'Client Code should be a short code (e.g. "ZACO"), not a full invoice number -- it gets combined with the date and sequence to build the invoice number automatically.',
        ];
    }
}
