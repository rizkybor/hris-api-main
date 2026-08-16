<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LetterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'letter_code_id' => ['required', 'integer', 'exists:letter_codes,id'],
            'division_code_id' => ['required', 'integer', 'exists:division_codes,id'],
            'type' => ['required', 'in:I,E'],
            'date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
            'body' => ['required', 'string'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.specification' => ['nullable', 'string'],
            'items.*.qty' => ['nullable', 'string', 'max:50'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'second_party_name' => ['nullable', 'string', 'max:255'],
            'second_party_signatory_name' => ['nullable', 'string', 'max:255'],
            'second_party_signatory_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
