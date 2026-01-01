<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyAboutStoreUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'vision' => ['nullable', 'string'],
            'mission' => ['nullable', 'string'],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['string'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'established_date' => ['sometimes', 'required', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Company Name',
            'description' => 'Description',
            'vision' => 'Vision',
            'mission' => 'Mission',
            'branches' => 'Branches',
            'branches.*' => 'Branch',
            'address' => 'Address',
            'email' => 'Email',
            'phone' => 'Phone',
            'established_date' => 'Established Date',
        ];
    }
}
