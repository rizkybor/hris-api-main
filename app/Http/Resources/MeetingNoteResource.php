<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class MeetingNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'title' => $this->title,
            'meeting_type' => $this->meeting_type,
            'meeting_date' => $this->meeting_date,
            'body' => $this->body,
            'action_items' => $this->action_items ?? [],
            'external_attendees' => $this->external_attendees ?? [],
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null),
            'attendees' => $this->whenLoaded('attendees', fn () => $this->attendees->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
            ])),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'url' => asset('storage/'.$file->file_path),
                'mime_type' => $file->mime_type,
                'size_file' => $file->size_file,
            ])),
            'is_pinned' => $this->when(isset($this->is_pinned), fn () => (bool) $this->is_pinned),
            // Employees checked off in Internal Attendees may use the comment
            // feature, and so may the note's own creator even if they didn't
            // check themselves in as an attendee.
            'can_comment' => $this->when($this->relationLoaded('attendees'), function () {
                $user = Auth::user();
                if (! $user) {
                    return false;
                }

                $employeeId = $user->employeeProfile?->id;
                $isAttendee = $employeeId && $this->attendees->contains('id', $employeeId);
                $isCreator = $this->created_by === $user->id;

                return $isAttendee || $isCreator;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
