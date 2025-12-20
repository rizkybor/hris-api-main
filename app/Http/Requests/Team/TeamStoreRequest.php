<?php

namespace App\Http\Requests\Team;

use App\Enums\Department;
use App\Enums\TeamStatus;
use Illuminate\Foundation\Http\FormRequest;

class TeamStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expected_size' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'icon' => ['required', 'image', 'max:2048'],
            'department' => ['required', 'string', 'in:'.implode(',', array_column(Department::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(TeamStatus::cases(), 'value'))],
            'team_lead_id' => ['nullable', 'exists:users,id'],
            'responsibilities' => ['required', 'array', 'min:3'],
            'responsibilities.*' => ['required', 'string'],
        ];
    }
}
