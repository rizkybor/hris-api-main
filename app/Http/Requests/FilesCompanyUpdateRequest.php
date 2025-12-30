<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilesCompanyUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'required', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'path' => 'Path',
            'name' => 'Name',
            'description' => 'Description',
        ];
    }
}

