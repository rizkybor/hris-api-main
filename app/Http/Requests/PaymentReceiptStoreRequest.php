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
            'client_code' => ['required', 'string', 'max:20'],
            'date' => ['required', 'date'],
            'received_from' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'for_payment_of' => ['required', 'string'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'payment_status' => ['required', 'in:paid,partial'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
