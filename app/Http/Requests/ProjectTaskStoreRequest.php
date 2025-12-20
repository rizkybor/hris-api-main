<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;

class ProjectTaskStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:employee_profiles,id'],
            'priority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(TaskStatus::cases(), 'value'))],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
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
