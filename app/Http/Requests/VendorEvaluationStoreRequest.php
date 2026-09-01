<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorEvaluationStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evaluated_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'vendor_id' => 'Vendor',
            'rating' => 'Rating',
            'notes' => 'Catatan',
            'evaluated_at' => 'Tanggal Evaluasi',
        ];
    }
}
