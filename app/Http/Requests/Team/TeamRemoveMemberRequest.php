<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class TeamRemoveMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employee_profiles,id'],
        ];
    }

    public function attributes()
    {
        return [
            'employee_id' => 'Employee ID',
        ];
    }
}
