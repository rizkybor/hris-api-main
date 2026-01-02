<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsTaskListUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'pivot_id' => ['sometimes', 'exists:vendors_task_pivots,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'Task Name',
            'pivot_id' => 'Pivot',
        ];
    }
}
