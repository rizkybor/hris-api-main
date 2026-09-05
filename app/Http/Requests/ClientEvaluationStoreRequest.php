<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientEvaluationStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evaluated_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'Client',
            'rating' => 'Rating',
            'notes' => 'Catatan',
            'evaluated_at' => 'Tanggal Evaluasi',
        ];
    }
}
