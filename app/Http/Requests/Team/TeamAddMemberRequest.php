<?php

namespace App\Http\Requests\Team;

use App\Rules\NotProtectedEmployee;
use Illuminate\Foundation\Http\FormRequest;

class TeamAddMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employee_profiles,id', new NotProtectedEmployee('a team member')],
        ];
    }

    public function attributes()
    {
        return [
            'employee_id' => 'Employee ID',
        ];
    }
}
