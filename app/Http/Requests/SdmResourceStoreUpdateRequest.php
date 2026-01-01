<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\SdmResourceStatus;

class SdmResourceStoreUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sdm_component' => ['sometimes', 'required', 'string', 'max:255'],
            'metrik' => ['sometimes', 'required', 'string', 'max:255'],
            'capacity_target' => ['sometimes', 'required', 'string', 'max:255'],
            'actual' => ['nullable', 'required', 'numeric', 'min:0'],
            'rag_status' => ['sometimes','required', 'string', 'in:'.implode(',', array_column(SdmResourceStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'sdm_component' => 'SDM Component',
            'metrik' => 'Metrik',
            'capacity_target' => 'Capacity Target',
            'actual' => 'Actual',
            'rag_status' => 'RAG Status',
        ];
    }
}
