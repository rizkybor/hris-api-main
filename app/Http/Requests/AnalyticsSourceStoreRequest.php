<?php

namespace App\Http\Requests;

use App\Enums\AnalyticsSourceType;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsSourceStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(AnalyticsSourceType::cases(), 'value'))],
            'category' => ['required', 'string', 'max:255'],
            'embed_url' => ['required', 'url', 'max:4096'],
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
