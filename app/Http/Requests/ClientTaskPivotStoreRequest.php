<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientTaskPivotStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id'       => ['required', 'exists:clients,id'],
            'scope_client_id' => ['nullable', 'exists:client_task_scopes,id'],
            'task_client_id'  => ['nullable', 'exists:client_task_lists,id'],
            'payment_client_id' => ['nullable', 'exists:client_task_payments,id'],
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
            'client_id'       => 'Client',
            'scope_client_id' => 'Scope Client',
            'task_client_id'  => 'Task Client',
            'payment_client_id' => 'Task Payment',
            'maintenance'     => 'Maintenance',
            'contract_value'  => 'Contract Value',
            'contract_status' => 'Contract Status',
            'contract_start'  => 'Contract Start Date',
            'contract_end'    => 'Contract End Date',
        ];
    }
}
