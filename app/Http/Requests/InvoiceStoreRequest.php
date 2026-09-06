<?php

namespace App\Http\Requests;

use App\Enums\PphType;
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
            // "automatic": client_code (a short code, e.g. "ZACO") is combined
            // with the date and sequence into INV/JCD-{code}/DDMM/YY.NNN.
            // "manual": invoice_number is used verbatim as typed.
            'numbering_mode' => ['required', 'in:automatic,manual'],
            // No "/" allowed: this is a short code slotted into the
            // auto-generated invoice number -- a value that already
            // contains "/" (e.g. someone pasting a full invoice number in
            // here instead of just the code) doubles up and breaks the
            // generated number. Only required in automatic mode.
            'client_code' => ['required_if:numbering_mode,automatic', 'nullable', 'string', 'max:40', 'regex:/^[^\/]+$/'],
            'invoice_number' => ['required_if:numbering_mode,manual', 'nullable', 'string', 'max:255', 'unique:invoices,invoice_number'],
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
            // Optional pass-through fee (e.g. ICANN's registrar fee on a
            // domain) -- not every invoice has one, and it's not part of
            // the VAT/PPN taxable base.
            'icann_fee' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string'],
            // Purely informational heads-up that the client is expected to
            // withhold PPh 23 on payment -- doesn't affect subtotal/total.
            'pph23_type' => ['nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph23_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_code.regex' => 'Client Code should be a short code (e.g. "ZACO"), not a full invoice number -- it gets combined with the date and sequence to build the invoice number automatically.',
            'client_code.required_if' => 'Client Code is required for automatic numbering.',
            'invoice_number.required_if' => 'Invoice Number is required for manual numbering.',
            'invoice_number.unique' => 'This invoice number is already in use.',
        ];
    }
}
