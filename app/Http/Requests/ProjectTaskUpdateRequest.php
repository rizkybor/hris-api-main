<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;

class ProjectTaskUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
            'priority' => ['sometimes', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(TaskStatus::cases(), 'value'))],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert empty string to null for assignee_id
        if ($this->has('assignee_id') && $this->assignee_id === '') {
            $this->merge([
                'assignee_id' => null,
            ]);
        }
    }

    public function attributes()
    {
        return [
            'project_id' => 'Project',
            'name' => 'Task Name',
            'description' => 'Description',
            'assignee_id' => 'Assignee',
            'priority' => 'Priority',
            'status' => 'Status',
            'due_date' => 'Due Date',
        ];
    }
}
