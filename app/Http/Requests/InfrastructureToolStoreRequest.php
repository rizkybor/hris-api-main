<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\InfrastructureToolStatus;

class InfrastructureToolStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tech_stack_component' => ['required', 'string', 'max:255'],
            'vendor' => ['required', 'string', 'max:255'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'annual_fee' => ['nullable', 'numeric', 'min:0'],
            'expired_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(InfrastructureToolStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'tech_stack_component' => 'Tech Stack Component',
            'vendor' => 'Vendor',
            'monthly_fee' => 'Monthly Fee',
            'annual_fee' => 'Annual Fee',
            'expired_date' => 'Expired Date',
            'status' => 'Status',
            'notes' => 'Notes',
        ];
    }
}
