<?php

namespace App\Http\Requests;

use App\Enums\PphType;
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
            // "automatic": client_code (a short code, e.g. "ZACO") is combined
            // with the date and sequence into RCP/JCD-{code}/DDMM/YY.NNN.
            // "manual": receipt_number is used verbatim as typed.
            'numbering_mode' => ['required', 'in:automatic,manual'],
            // No "/" allowed: this is a short code slotted into the
            // auto-generated receipt number -- a value that already
            // contains "/" (e.g. someone pasting a full receipt/invoice
            // number in here instead of just the code) doubles up and
            // breaks the generated number. Only required in automatic mode.
            'client_code' => ['required_if:numbering_mode,automatic', 'nullable', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'receipt_number' => ['required_if:numbering_mode,manual', 'nullable', 'string', 'max:255', 'unique:payment_receipts,receipt_number'],
            'date' => ['required', 'date'],
            'received_from' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            // Withheld by the client from what they pay -- amount above is
            // already what was actually received (net of this).
            'pph23_type' => ['nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph23_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_amount' => ['nullable', 'numeric', 'min:0'],
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
            'client_code.required_if' => 'Client Code is required for automatic numbering.',
            'receipt_number.required_if' => 'Receipt Number is required for manual numbering.',
            'receipt_number.unique' => 'This receipt number is already in use.',
        ];
    }
}
