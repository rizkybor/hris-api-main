<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'pic_name' => ['sometimes', 'required', 'string', 'max:255'],
            'pic_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:100'],
            'field' => ['nullable', 'string', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'siup_number' => ['nullable', 'string', 'max:100'],
            'nib_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Client Name',
            'pic_name' => 'PIC Name',
            'pic_phone' => 'PIC Phone',
            'email' => 'Email',
            'address' => 'Address',
            'type' => 'Client Type',
            'field' => 'Client Field',
            'npwp' => 'NPWP',
            'siup_number' => 'Nomor SIUP',
            'nib_number' => 'Nomor NIB',
            'notes' => 'Notes',
        ];
    }
}
