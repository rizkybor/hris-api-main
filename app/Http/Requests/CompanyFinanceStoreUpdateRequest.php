<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyFinanceStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'saldo_company' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes()
    {
        return [
            'saldo_company' => 'Saldo Company',
        ];
    }
}
