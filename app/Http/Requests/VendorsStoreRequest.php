<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:100'],
            'field' => ['nullable', 'string', 'max:100'],
            // All optional -- a vendor may be an individual (perorangan)
            // rather than a registered company (PT/CV), so none of these
            // legal identifiers are guaranteed to exist.
            'npwp' => ['nullable', 'string', 'max:50'],
            'siup_number' => ['nullable', 'string', 'max:100'],
            'nib_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Vendor Name',
            'pic_name' => 'PIC Name',
            'pic_phone' => 'PIC Phone',
            'email' => 'Email',
            'address' => 'Address',
            'type' => 'Vendor Type',
            'field' => 'Vendor Field',
            'npwp' => 'NPWP',
            'siup_number' => 'Nomor SIUP',
            'nib_number' => 'Nomor NIB',
            'notes' => 'Notes',
        ];
    }
}
