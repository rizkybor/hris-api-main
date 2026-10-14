<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
{
    private bool $includeSections = false;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'proposal_date' => $this->proposal_date?->toDateString(),
            'letter_number' => $this->letter_number,
            'status' => $this->status,
            'cover_image' => $this->cover_image,
            'cover_image_url' => CloudinaryUrl::image($this->cover_image),
            'signatory_name' => $this->signatory_name,
            'signatory_title' => $this->signatory_title,
            'use_signature' => (bool) $this->use_signature,
            'section_count' => count($this->sections ?? []),
            // The body is only sent when one proposal is opened -- the list
            // never needs it and it can be large.
            'sections' => $this->when($this->includeSections, $this->sections ?? []),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater ? ['id' => $this->updater->id, 'name' => $this->updater->name] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function withSections(): static
    {
        $this->includeSections = true;

        return $this;
    }
}
