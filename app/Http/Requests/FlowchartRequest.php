<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlowchartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'data' => ['required', 'array'],
            'data.nodes' => ['present', 'array', 'max:500'],
            'data.edges' => ['present', 'array', 'max:1000'],
            'data.viewport' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'description' => 'Description',
            'data' => 'Diagram',
            'data.nodes' => 'Shapes',
            'data.edges' => 'Connectors',
        ];
    }
}
