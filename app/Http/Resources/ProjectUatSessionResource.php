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
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $locations = $this->relationLoaded('locations') ? $this->locations : $this->locations()->get();
        $placeNames = $locations->pluck('location', 'id');

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'round' => $this->round,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            // Findings (temuan) and notes (catatan), each optionally tied to
            // a feature and a menu.
            // Places held at the same time (UAT 1 at the client's office and
            // at a branch, say).
            'locations' => $locations->map(fn ($location) => [
                'id' => $location->id,
                'location' => $location->location,
                'date' => $location->held_on?->format('Y-m-d'),
                'time' => $location->start_time,
                'participants' => $location->participants,
                'items_count' => $items->where('project_uat_location_id', $location->id)->count(),
            ])->values(),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'location_id' => $item->project_uat_location_id,
                'location' => $placeNames[$item->project_uat_location_id] ?? null,
                'type' => $item->type,
                'feature' => $item->feature,
                'menu' => $item->menu,
                'description' => $item->description,
                'is_resolved' => (bool) $item->is_resolved,
                'resolved_at' => $item->resolved_at,
                'resolved_by' => $item->resolver?->name,
                'photo' => $item->photo_path ? [
                    'url' => CloudinaryUrl::image($item->photo_path),
                    'name' => $item->photo_name,
                ] : null,
            ])->values(),
            'resolved_count' => $items->where('is_resolved', true)->count(),
            'findings_count' => $items->where('type', 'finding')->count(),
            'notes_count' => $items->where('type', 'note')->count(),
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
