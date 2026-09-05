<?php

namespace App\Http\Requests;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Rules\NotProtectedEmployee;
use Illuminate\Foundation\Http\FormRequest;

class ProjectUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(ProjectType::cases(), 'value'))],
            'priority' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(ProjectPriority::cases(), 'value'))],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'warranty_period_months' => ['nullable', 'integer', 'min:0'],
            'retention_period_months' => ['nullable', 'integer', 'min:0'],
            'project_leader_id' => ['nullable', 'exists:employee_profiles,id', new NotProtectedEmployee('Project Leader')],
            'client_id' => ['nullable', 'exists:clients,id'],
            // Free-text HTML from the RichTextEditor -- only the Project
            // Leader may actually change it (enforced in the controller,
            // not here, since that requires the existing Project + auth
            // context this FormRequest doesn't have).
            'inspect_note' => ['nullable', 'string'],
            'team_assignment_mode' => ['sometimes', 'required', 'string', 'in:team,employee'],
            'team_id' => ['required_if:team_assignment_mode,team', 'nullable', 'integer', 'exists:teams,id'],
            'member_employee_ids' => ['nullable', 'array'],
            'member_employee_ids.*' => ['integer', 'exists:employee_profiles,id', new NotProtectedEmployee('a project member')],
            'access_project_name' => ['nullable', 'string', 'max:255'],
            'access_project_url' => ['nullable', 'url', 'max:2048'],
            'access_github_name' => ['nullable', 'string', 'max:255'],
            'access_github_url' => ['nullable', 'url', 'max:2048'],
            'access_figma_name' => ['nullable', 'string', 'max:255'],
            'access_figma_url' => ['nullable', 'url', 'max:2048'],
            'additional_access' => ['nullable', 'array'],
            'additional_access.*.name' => ['required_with:additional_access.*.url', 'nullable', 'string', 'max:255'],
            'additional_access.*.url' => ['required_with:additional_access.*.name', 'nullable', 'url', 'max:2048'],
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
            'warranty_period_months' => 'Warranty Period',
            'retention_period_months' => 'Retention Period',
            'project_leader_id' => 'Project Leader',
            'client_id' => 'Client',
            'team_assignment_mode' => 'Team Assignment Mode',
            'team_id' => 'Team',
            'member_employee_ids' => 'Members',
            'access_project_name' => 'Access Project Name',
            'access_project_url' => 'Access Project Link',
            'access_github_name' => 'Access Github Name',
            'access_github_url' => 'Access Github Link',
            'access_figma_name' => 'Access Figma Name',
            'access_figma_url' => 'Access Figma Link',
        ];
    }
}
