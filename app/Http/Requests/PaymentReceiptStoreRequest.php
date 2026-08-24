<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentReceiptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // No "/" allowed: this is a short code slotted into the
            // auto-generated receipt number (RCP/JCD-{client_code}/DDMM/
            // YY.NNN) -- a value that already contains "/" (e.g. someone
            // pasting a full receipt/invoice number in here instead of
            // just the code) doubles up and breaks the generated number.
            'client_code' => ['required', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'date' => ['required', 'date'],
            'received_from' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'for_payment_of' => ['required', 'string'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'payment_status' => ['required', 'in:paid,partial'],
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
