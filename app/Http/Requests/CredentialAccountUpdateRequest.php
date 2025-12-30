<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CredentialAccountUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label_password' => ['sometimes', 'required', 'string', 'max:255'],
            'username_email' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'label_password' => 'Label Password',
            'username_email' => 'Username/Email',
            'password' => 'Password',
            'website' => 'Website',
            'notes' => 'Notes',
        ];
    }
}

