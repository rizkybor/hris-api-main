<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyFinanceStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'saldo_company' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes()
    {
        return [
            'saldo_company' => 'Saldo Company',
        ];
    }
}

