<?php

namespace App\Http\Requests;

use App\Enums\TaskColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectTaskUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'assignee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:employee_profiles,id'],
            'priority' => ['sometimes', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(TaskStatus::cases(), 'value'))],
            'due_date' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'in:'.implode(',', array_column(TaskColor::cases(), 'value'))],
            // Only sent by the Kanban drag-and-drop move action -- a
            // fractional position within the task's (project_id, status)
            // group, computed client-side from its new neighbors.
            'position' => ['nullable', 'numeric'],
        ];
    }

    /**
     * assignee_ids is the list of assignees; "" (sent by a form to clear it)
     * means none. A lone assignee_id from older clients becomes a list of one.
     */
    protected function prepareForValidation()
    {
        if ($this->has('assignee_ids') && ($this->assignee_ids === '' || $this->assignee_ids === null)) {
            $this->merge(['assignee_ids' => []]);
        }
        if ($this->has('assignee_id') && $this->assignee_id === '') {
            $this->merge(['assignee_id' => null]);
        }
        if (! $this->has('assignee_ids') && $this->has('assignee_id')) {
            $this->merge(['assignee_ids' => $this->assignee_id ? [(int) $this->assignee_id] : []]);
        }
    }

    public function attributes()
    {
        return [
            'project_id' => 'Project',
            'name' => 'Task Name',
            'description' => 'Description',
            'image' => 'Image',
            'assignee_id' => 'Assignee',
            'assignee_ids' => 'Assignees',
            'priority' => 'Priority',
            'status' => 'Status',
            'due_date' => 'Due Date',
            'type' => 'Task Type',
            'color' => 'Color',
            'position' => 'Position',
        ];
    }
}
