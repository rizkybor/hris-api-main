<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilesCompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * RULE VALIDASI INPUT USER UNTUK UPDATE
     */
    public function rules(): array
    {
        return [
            'document_path' => ['sometimes', 'file', 'mimes:pdf,doc,docx,xlsx,png,jpg,jpeg', 'max:10240'],
            'document_name' => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
        ];
    }

    /**
     * NAMA FIELD UNTUK PESAN ERROR
     */
    public function attributes(): array
    {
        return [
            'document_path' => 'File Dokumen',
            'document_name' => 'Nama Dokumen',
            'description'   => 'Deskripsi',
        ];
    }
}
