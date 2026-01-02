<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixedCostUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'financial_items' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'budget' => ['nullable', 'required', 'numeric', 'min:0'],
            'actual' => ['nullable', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'financial_items' => 'Financial Items',
            'description' => 'Description',
            'budget' => 'Budget',
            'actual' => 'Actual',
            'notes' => 'Notes',
        ];
    }
}

