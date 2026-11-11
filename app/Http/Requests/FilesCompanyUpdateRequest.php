<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // A company file always has a file (document_path is NOT NULL),
            // so removing the current one needs a replacement in the same
            // request. Checked here, before the controller deletes anything
            // from Cloudinary.
            'document_path' => [Rule::requiredIf(fn () => $this->boolean('remove_file')), 'file', 'mimes:pdf,doc,docx,xlsx,png,jpg,jpeg', 'max:10240'],
            'remove_file' => ['sometimes', 'boolean'],
            'document_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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
            'description' => 'Deskripsi',
        ];
    }

    public function messages(): array
    {
        return [
            'document_path.required' => 'Unggah file pengganti sebelum menghapus file lama.',
        ];
    }
}
