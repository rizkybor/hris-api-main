<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AttendanceCheckInRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'check_in_lat' => ['required', 'numeric'],
            'check_in_long' => ['required', 'numeric'],
            'check_in_photo' => ['required', 'string', 'starts_with:data:image/', 'max:5000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes()
    {
        return [
            'employee_id' => 'Employee',
            'check_in_lat' => 'Latitude',
            'check_in_long' => 'Longitude',
            'check_in_photo' => 'Photo',
            'notes' => 'Catatan',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_id' => Auth::user()->employeeProfile?->id,
        ]);
    }
}
