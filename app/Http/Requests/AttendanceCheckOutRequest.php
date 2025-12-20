<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCheckOutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'check_out_lat' => ['required', 'numeric'],
            'check_out_long' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes()
    {
        return [
            'check_out_lat' => 'Latitude',
            'check_out_long' => 'Longitude',
            'notes' => 'Notes',
        ];
    }
}
