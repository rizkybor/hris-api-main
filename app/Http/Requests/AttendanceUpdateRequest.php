<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceUpdateRequest extends FormRequest
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
            'employee_id' => ['sometimes', 'required', 'integer', 'exists:employee_profiles,id'],
            'date' => ['sometimes', 'required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i:s'],
            'check_in_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'check_in_long' => ['nullable', 'numeric', 'between:-180,180'],
            'check_out' => ['nullable', 'date_format:H:i:s', 'after:check_in'],
            'check_out_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'check_out_long' => ['nullable', 'numeric', 'between:-180,180'],
            'total_hours' => ['nullable', 'date_format:H:i:s'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(AttendanceStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes()
    {
        return [
            'employee_id' => 'Employee',
            'date' => 'Date',
            'check_in' => 'Check In Time',
            'check_in_lat' => 'Check In Latitude',
            'check_in_long' => 'Check In Longitude',
            'check_out' => 'Check Out Time',
            'check_out_lat' => 'Check Out Latitude',
            'check_out_long' => 'Check Out Longitude',
            'total_hours' => 'Total Working Hours',
            'status' => 'Status',
            'notes' => 'Notes',
        ];
    }
}
