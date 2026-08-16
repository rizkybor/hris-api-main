<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'is_pinned' => (bool) $this->is_pinned,
            'expires_at' => $this->expires_at,
            'created_by' => $this->when($this->relationLoaded('creator'), fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
