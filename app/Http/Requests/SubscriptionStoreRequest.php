<?php

namespace App\Http\Requests;

use App\Enums\PphType;
use App\Models\ConfigurableOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Configurable via Settings -> Dropdown Options rather than a
            // fixed enum, so a new service type doesn't need a deploy.
            'service_type' => ['required', 'string', Rule::in(
                ConfigurableOption::category('subscription_service_type')->active()->pluck('value')
            )],
            'product_name' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'billing_cycle' => ['required', 'string', 'in:monthly,yearly'],
            'amount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'next_due_date' => ['required', 'date'],
            'status' => ['sometimes', 'string', 'in:active,postponed,cancelled'],
            'notes' => ['nullable', 'string'],
            // Invoice configuration, reused for every period's generated
            // invoice so it doesn't need to be re-entered each time.
            'ppn_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['required', 'string', 'max:255'],
            'terms' => ['nullable', 'string'],
            'pph23_type' => ['nullable', 'string', 'in:'.implode(',', array_column(PphType::cases(), 'value'))],
            'pph23_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'service_type' => 'Service Type',
            'product_name' => 'Product Name',
            'project_id' => 'Project',
            'client_id' => 'Client',
            'billing_cycle' => 'Billing Cycle',
            'amount' => 'Amount',
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
