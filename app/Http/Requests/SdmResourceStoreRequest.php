<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'actual' => ['required', 'numeric', 'min:0'],
            'rag_status' => ['required', 'string', 'in:active,inactive'],
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
