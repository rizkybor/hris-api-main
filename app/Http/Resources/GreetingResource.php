<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GreetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'greeting_date' => $this->greeting_date?->toDateString(),
            'is_recurring_yearly' => (bool) $this->is_recurring_yearly,
            'type' => $this->type,
            'audience' => $this->audience,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->when($this->relationLoaded('creator'), fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
