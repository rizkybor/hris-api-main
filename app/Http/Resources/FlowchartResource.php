<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowchartResource extends JsonResource
{
    private bool $includeData = false;

    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'prepared_for' => $this->prepared_for,
            'node_count' => count($data['nodes'] ?? []),
            'edge_count' => count($data['edges'] ?? []),
            'preview_url' => CloudinaryUrl::image($this->preview_image),
            // The diagram itself is only sent when a single flowchart is
            // opened -- the list never needs it and it can be large.
            'data' => $this->when($this->includeData, $data),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function withData(): static
    {
        $this->includeData = true;

        return $this;
    }
}
