<?php

namespace App\Http\Requests;

use App\Enums\LeaveType;
use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'sometimes|exists:employee_profiles,id',
            'leave_type' => 'sometimes|string|in:'.implode(',', array_column(LeaveType::cases(), 'value')),
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'total_days' => 'nullable|integer|min:1',
            'reason' => 'sometimes|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'approved_by' => 'nullable|exists:employee_profiles,id',
        ];
    }
}
