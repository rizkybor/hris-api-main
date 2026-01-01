<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixedCostStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'financial_items' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric', 'min:0'],
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
