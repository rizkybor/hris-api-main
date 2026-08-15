<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LetterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note: letter_code, division_code, type and date are intentionally not editable
     * here because they are baked into the already-issued letter_number. Changing them
     * would desynchronize the printed number from the stored data. Cancel and reissue
     * a new letter instead.
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
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
