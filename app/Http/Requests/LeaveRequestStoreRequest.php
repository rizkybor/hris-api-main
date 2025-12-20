<?php

namespace App\Http\Requests;

use App\Enums\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LeaveRequestStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employee_profiles,id',
            'leave_type' => 'required|string|in:'.implode(',', array_column(LeaveType::cases(), 'value')),
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|integer|min:1',
            'reason' => 'required|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ];
    }

    public function attributes()
    {
        return [
            'employee_id' => 'Employee',
            'leave_type' => 'Leave Type',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'employee_id' => Auth::user()->employeeProfile->id,
        ]);
    }
}
