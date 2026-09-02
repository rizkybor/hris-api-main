<?php

namespace App\Http\Requests;

use App\Enums\AnalyticsSourceType;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsSourceUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(AnalyticsSourceType::cases(), 'value'))],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'embed_url' => ['sometimes', 'required', 'url', 'max:4096'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'website_url' => 'Website URL',
            'type' => 'Type',
            'category' => 'Category',
            'embed_url' => 'Embed URL',
        ];
    }
}
