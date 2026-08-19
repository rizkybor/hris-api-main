<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'size_bytes' => $this->size_bytes,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name ?? 'System (Scheduled)'),
            'is_automatic' => (bool) $this->is_automatic,
            'created_at' => $this->created_at,
        ];
    }
}
