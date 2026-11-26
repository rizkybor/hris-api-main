<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectUatSessionResource extends JsonResource
{
    private ?int $planned = null;

    public function withPlan(?int $planned): static
    {
        $this->planned = $planned;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $findings = $this->findingList();

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'round' => $this->round,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'findings' => $this->findings,
            'findings_count' => count($findings),
            // A round past the number agreed with the client.
            'over_plan' => $this->planned !== null && $this->round > $this->planned,
            'attachment' => $this->attachment_path ? [
                'url' => CloudinaryUrl::raw($this->attachment_path),
                'name' => $this->attachment_name,
                'type' => $this->attachment_type,
                'size' => $this->attachment_size,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
