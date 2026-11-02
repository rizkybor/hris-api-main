<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientAttachmentUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id'     => ['sometimes', 'required', 'exists:clients,id'],
            'document_name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_path' => ['sometimes', 'required', 'string', 'max:500'],
            'type_file'     => ['nullable', 'string', 'max:100'],
            'size_file'     => ['nullable', 'string', 'max:50'],
            'description'   => ['nullable', 'string'],
            // The uploaded file itself (sent to Cloudinary): documents and images only.
            'file'          => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'      => 'Client',
            'document_name' => 'Document Name',
            'document_path' => 'Document Path',
            'type_file'     => 'File Type',
            'size_file'     => 'File Size',
            'description'   => 'Description',
        ];
    }
}
