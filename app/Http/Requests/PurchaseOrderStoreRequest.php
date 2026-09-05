<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:I,E'],
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_address' => ['nullable', 'string'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_wa' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.qty' => ['required', 'string', 'max:50'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'array'],
            'payment_terms.*.termin' => ['required_with:payment_terms', 'string', 'max:255'],
            'payment_terms.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms.*.description' => ['nullable', 'string'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'replacement_days' => ['nullable', 'integer', 'min:0'],
            'buyer_signatory_name' => ['nullable', 'string', 'max:255'],
            'buyer_signatory_title' => ['nullable', 'string', 'max:255'],
            'client_signatory_name' => ['nullable', 'string', 'max:255'],
            'client_signatory_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
