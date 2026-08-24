<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentReceiptUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // See PaymentReceiptStoreRequest for why "/" is disallowed here.
            'client_code' => ['sometimes', 'required', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'date' => ['sometimes', 'required', 'date'],
            'received_from' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'for_payment_of' => ['sometimes', 'required', 'string'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'payment_status' => ['sometimes', 'required', 'in:paid,partial'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_code.regex' => 'Client Code should be a short code (e.g. "ZACO"), not a full receipt/invoice number -- it gets combined with the date and sequence to build the receipt number automatically.',
        ];
    }
}
