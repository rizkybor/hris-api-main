<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsTaskPivotStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vendor_id'       => ['required', 'exists:vendors,id'],
            'scope_vendor_id' => ['required', 'exists:vendors_task_scopes,id'],
            'task_vendor_id'  => ['required', 'exists:vendors_task_lists,id'],
            'task_payment_id' => ['nullable', 'exists:vendors_task_paymentss,id'],
            'maintenance'     => ['nullable', 'boolean'],
            'contract_value'  => ['nullable', 'numeric', 'min:0'],
            'contract_status' => ['nullable', 'string', 'max:100'],
            'contract_start'  => ['nullable', 'date'],
            'contract_end'    => ['nullable', 'date', 'after_or_equal:contract_start'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vendor_id'       => 'Vendor',
            'scope_vendor_id' => 'Scope Vendor',
            'task_vendor_id'  => 'Task Vendor',
            'task_payment_id' => 'Task Payment',
            'maintenance'     => 'Maintenance',
            'contract_value'  => 'Contract Value',
            'contract_status' => 'Contract Status',
            'contract_start'  => 'Contract Start Date',
            'contract_end'    => 'Contract End Date',
        ];
    }
}
