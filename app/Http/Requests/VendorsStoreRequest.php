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
            'notes' => 'Notes',
        ];
    }
}
