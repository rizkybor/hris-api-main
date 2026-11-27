<?php

namespace App\Http\Resources;

use App\Models\ProjectUatItem;
use App\Models\ProjectUatLocation;
use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One UAT (round) with its sessions; each session carries its own findings
 * and notes.
 */
class ProjectUatSessionResource extends JsonResource
{
    /** Items per session sent with the UAT; more are fetched on scroll. */
    public const FIRST_PAGE = 5;

    private ?int $planned = null;

    public function withPlan(?int $planned): static
    {
        $this->planned = $planned;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $locations = $this->relationLoaded('locations') ? $this->locations : $this->locations()->with('items')->get();
        $items = $locations->flatMap(fn (ProjectUatLocation $location) => $location->items);

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'round' => $this->round,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'findings_count' => $items->where('type', ProjectUatItem::TYPE_FINDING)->count(),
            'notes_count' => $items->where('type', ProjectUatItem::TYPE_NOTE)->count(),
            'items_count' => $items->count(),
            'resolved_count' => $items->where('is_resolved', true)->count(),
            // Sessions held at the same time in different places.
            'locations' => $locations->values()->map(fn (ProjectUatLocation $location, int $index) => [
                'id' => $location->id,
                'number' => $index + 1,
                'location' => $location->location,
                'date' => $location->held_on?->format('Y-m-d'),
                'time' => $location->start_time,
                'participants' => $location->participants,
                'findings_count' => $location->items->where('type', ProjectUatItem::TYPE_FINDING)->count(),
                'notes_count' => $location->items->where('type', ProjectUatItem::TYPE_NOTE)->count(),
                'resolved_count' => $location->items->where('is_resolved', true)->count(),
                // Only the first page; the rest is fetched while scrolling
                // (GET .../uat/locations/{id}/items?offset=&limit=).
                'items' => $location->items->take(self::FIRST_PAGE)->map(fn (ProjectUatItem $item) => self::item($item))->values(),
                'items_total' => $location->items->count(),
            ])->values(),
            // A UAT past the number agreed with the client.
            'over_plan' => $this->planned !== null && $this->round > $this->planned,
            // The generated Berita Acara (numbered letter), if any.
            'berita_acara' => $this->ba_letter_id && $this->beritaAcara ? [
                'id' => $this->beritaAcara->id,
                'letter_number' => $this->beritaAcara->letter_number,
                'date' => $this->beritaAcara->date?->toDateString(),
                'status' => $this->beritaAcara->status,
            ] : null,
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

    public static function item(ProjectUatItem $item): array
    {
        return [
            'id' => $item->id,
            'location_id' => $item->project_uat_location_id,
            'type' => $item->type,
            'feature' => $item->feature,
            'menu' => $item->menu,
            'description' => $item->description,
            'is_resolved' => (bool) $item->is_resolved,
            'resolved_at' => $item->resolved_at,
            'resolved_by' => $item->resolver?->name,
            // Revision proof (next to "Sudah direvisi").
            'photo' => self::file($item->photo_path, $item->photo_name, $item->photo_kind),
            // File showing the issue, attached with the finding/note.
            'attachment' => self::file($item->evidence_path, $item->evidence_name, $item->evidence_kind),
        ];
    }

    /** @return array{url: ?string, name: ?string, kind: string, is_image: bool}|null */
    private static function file(?string $path, ?string $name, ?string $kind): ?array
    {
        if (! $path) {
            return null;
        }
        $isFile = $kind === 'file';

        return [
            'url' => $isFile ? CloudinaryUrl::raw($path) : CloudinaryUrl::image($path),
            'name' => $name,
            'kind' => $isFile ? 'file' : 'image',
            'is_image' => ! $isFile,
        ];
    }
}
