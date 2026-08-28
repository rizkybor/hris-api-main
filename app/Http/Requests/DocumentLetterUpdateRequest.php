<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentLetterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('document_letters', 'document_number')->ignore($this->route('document_letter'))],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'document_date' => ['sometimes', 'required', 'date'],
            'body' => ['sometimes', 'required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:5120'],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer', 'exists:document_letter_attachments,id'],
        ];
    }
}
