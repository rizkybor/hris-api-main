<?php

namespace App\Http\Requests;

use App\Enums\PphType;
use App\Models\ConfigurableOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'client_id' => ['sometimes', 'required', 'exists:clients,id'],
            'billing_cycle' => ['sometimes', 'required', 'string', 'in:monthly,yearly'],
            // Omitting `services` entirely leaves existing services
            // untouched; sending it replaces the full set (see
            // SubscriptionController::update()).
            'services' => ['sometimes', 'required', 'array', 'min:1'],
            'services.*.service_type' => ['required_with:services', 'string', Rule::in(
                ConfigurableOption::category('subscription_service_type')->active()->pluck('value')
            )],
            'services.*.product_name' => ['nullable', 'string', 'max:255'],
            'services.*.amount' => ['required_with:services', 'numeric', 'min:0'],
            'services.*.ppn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.notes' => ['nullable', 'string'],
            'start_date' => ['sometimes', 'required', 'date'],
            'next_due_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'string', 'in:active,postponed,cancelled'],
            'notes' => ['nullable', 'string'],
            'ppn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:255'],
            'terms' => ['nullable', 'string'],
            'pph23_type' => ['nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph23_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'services' => 'Services',
            'services.*.service_type' => 'Service Type',
            'services.*.product_name' => 'Product Name',
            'services.*.amount' => 'Amount',
            'services.*.ppn_percentage' => 'Service VAT / PPN Percentage',
            'project_id' => 'Project',
            'client_id' => 'Client',
            'billing_cycle' => 'Billing Cycle',
            'start_date' => 'Start Date',
            'next_due_date' => 'Next Due Date',
            'status' => 'Status',
            'notes' => 'Notes',
            'ppn_percentage' => 'PPN Percentage',
            'admin_fee' => 'Admin Fee',
            'bank_name' => 'Bank Account',
            'terms' => 'Terms',
            'pph23_type' => 'PPh 23 Type',
            'pph23_percent' => 'PPh 23 Percent',
        ];
    }
}
