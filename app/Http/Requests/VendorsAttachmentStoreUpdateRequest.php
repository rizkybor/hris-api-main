<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsAttachmentStoreUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document_name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_path' => ['sometimes', 'required', 'string', 'max:500'],
            'type_file'     => ['nullable', 'string', 'max:100'],
            'size_file'     => ['nullable', 'string', 'max:50'],
            'description'   => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_name' => 'Document Name',
            'document_path' => 'Document Path',
            'type_file'     => 'File Type',
            'size_file'     => 'File Size',
            'description'   => 'Description',
        ];
    }
}
