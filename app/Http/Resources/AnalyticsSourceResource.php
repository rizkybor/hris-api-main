<?php

namespace App\Http\Resources;

use App\Enums\AnalyticsSourceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = AnalyticsSourceType::tryFrom($this->type);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'website_url' => $this->website_url,
            'type' => $this->type,
            'type_label' => $type?->label() ?? $this->type,
            'category' => $this->category,
            'embed_url' => $this->embed_url,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
