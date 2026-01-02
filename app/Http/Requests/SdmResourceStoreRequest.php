<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\SdmResourceStatus;

class SdmResourceStoreRequest extends FormRequest
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
            'sdm_component' => ['required', 'string', 'max:255'],
            'metrik' => ['required', 'string', 'max:255'],
            'capacity_target' => ['required', 'string', 'max:255'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'actual' => ['nullable', 'numeric', 'min:0'],
            'rag_status' => ['required', 'string', 'in:'.implode(',', array_column(SdmResourceStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes()
    {
        return [
            'sdm_component' => 'SDM Component',
            'metrik' => 'Metrik',
            'capacity_target' => 'Capacity Target',
            'budget' => 'Budget',
            'actual' => 'Actual',
            'rag_status' => 'RAG Status',
            'notes' => 'Notes',
        ];
    }
}
