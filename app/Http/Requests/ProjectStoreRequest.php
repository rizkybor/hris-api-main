<?php

namespace App\Http\Requests;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Rules\NotProtectedEmployee;
use Illuminate\Foundation\Http\FormRequest;

class ProjectStoreRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ProjectType::cases(), 'value'))],
            'priority' => ['required', 'string', 'in:'.implode(',', array_column(ProjectPriority::cases(), 'value'))],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'project_leader_id' => ['nullable', 'exists:employee_profiles,id', new NotProtectedEmployee('Project Leader')],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            // "team": exactly one Team supplies both the leader and the
            // member roster. "employee": project_leader_id and
            // member_employee_ids are picked individually instead.
            'team_assignment_mode' => ['required', 'string', 'in:team,employee'],
            'team_id' => ['required_if:team_assignment_mode,team', 'nullable', 'integer', 'exists:teams,id'],
            'member_employee_ids' => ['nullable', 'array'],
            'member_employee_ids.*' => ['integer', 'exists:employee_profiles,id', new NotProtectedEmployee('a project member')],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Project Name',
            'type' => 'Project Type',
            'priority' => 'Priority',
            'status' => 'Status',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'description' => 'Description',
            'photo' => 'Project Photo',
            'budget' => 'Budget',
            'project_leader_id' => 'Project Leader',
            'vendor_id' => 'Vendor',
            'team_assignment_mode' => 'Team Assignment Mode',
            'team_id' => 'Team',
            'member_employee_ids' => 'Members',
        ];
    }
}
