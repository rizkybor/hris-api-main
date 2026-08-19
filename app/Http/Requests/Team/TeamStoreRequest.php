<?php

namespace App\Http\Requests\Team;

use App\Enums\Department;
use App\Enums\TeamStatus;
use App\Rules\NotProtectedEmployee;
use App\Rules\NotProtectedUser;
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
            // Explicit mimes list (matches every other upload in the app)
            // instead of the bare 'image' rule, which also accepts SVG --
            // an SVG can embed <script>, and unlike every other upload here
            // this one had no extension whitelist at all.
            'icon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'department' => ['required', 'string', 'in:'.implode(',', array_column(Department::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(TeamStatus::cases(), 'value'))],
            'team_lead_id' => ['nullable', 'exists:users,id', new NotProtectedUser('Team Lead')],
            'responsibilities' => ['required', 'array', 'min:3'],
            'responsibilities.*' => ['required', 'string'],
            'member_employee_ids' => ['nullable', 'array'],
            'member_employee_ids.*' => ['integer', 'exists:employee_profiles,id', new NotProtectedEmployee('a team member')],
        ];
    }
}
