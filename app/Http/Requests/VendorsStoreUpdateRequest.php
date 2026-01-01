<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsStoreUpdateRequest extends FormRequest
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
