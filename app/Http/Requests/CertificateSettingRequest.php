<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_code' => ['required', 'string', 'max:20'],
            'number_format' => ['required', 'string', 'max:255', 'regex:/\{sequence\}/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_code' => 'Kode Perusahaan',
            'number_format' => 'Format Nomor',
        ];
    }

    public function messages(): array
    {
        return [
            'number_format.regex' => 'Format Nomor wajib menyertakan token {sequence}.',
        ];
    }
}
