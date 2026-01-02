<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsTaskPaymentUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document_name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_path' => ['sometimes', 'required', 'string', 'max:500'],
            'amount'        => ['nullable', 'numeric', 'min:0'],
            'payment_date'  => ['nullable', 'date'],
            'pivot_id' => ['sometimes', 'exists:vendors_task_pivots,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_name' => 'Document Name',
            'document_path' => 'Document Path',
            'amount'        => 'Amount',
            'payment_date'  => 'Payment Date',
            'pivot_id' => 'Pivot',
        ];
    }
}
