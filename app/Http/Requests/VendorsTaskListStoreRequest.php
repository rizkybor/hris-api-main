<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorsTaskListStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'pivot_id' => ['required', 'exists:vendors_task_pivots,id'],
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
