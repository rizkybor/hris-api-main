<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateGenerateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'signatory_name' => ['required', 'string', 'max:255'],
            'signatory_title' => ['required', 'string', 'max:255'],
            'category_code' => ['required', 'string', 'max:50'],
            'program_code' => ['required', 'string', 'max:50'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],

            'recipients' => ['required', 'array', 'min:1', 'max:500'],
            'recipients.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Judul Sertifikat',
            'signatory_name' => 'Nama Penandatangan',
            'signatory_title' => 'Jabatan Penandatangan',
            'category_code' => 'Kode Kategori',
            'program_code' => 'Kode Program',
            'recipients' => 'Daftar Penerima',
        ];
    }
}
