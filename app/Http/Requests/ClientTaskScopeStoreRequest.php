<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientTaskScopeStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pivot_id' => ['required', 'exists:client_task_pivots,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Scope Name',
            'pivot_id' => 'Pivot',
        ];
    }
}
