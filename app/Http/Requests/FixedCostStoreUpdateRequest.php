<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixedCostStoreUpdateRequest extends FormRequest
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
            'budget' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function attributes()
    {
        return [
            'financial_items' => 'Financial Items',
            'description' => 'Description',
            'budget' => 'Budget',
        ];
    }
}

