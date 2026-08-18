<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentLetterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['nullable', 'string', 'max:255', 'unique:document_letters,document_number'],
            'subject' => ['required', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:5120'],
        ];
    }
}
