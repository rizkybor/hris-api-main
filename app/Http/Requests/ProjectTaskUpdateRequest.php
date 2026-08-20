<?php

namespace App\Http\Requests;

use App\Enums\TaskColor;
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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'assignee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
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
            'image' => 'Image',
            'assignee_id' => 'Assignee',
            'priority' => 'Priority',
            'status' => 'Status',
            'due_date' => 'Due Date',
            'type' => 'Task Type',
            'color' => 'Color',
            'position' => 'Position',
        ];
    }
}
