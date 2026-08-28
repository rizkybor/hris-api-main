<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffTaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'assignment_mode' => $this->assignment_mode,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assignees' => $this->whenLoaded('assignees', function () {
                return $this->assignees->map(fn ($assignee) => [
                    'id' => $assignee->id,
                    'employee_id' => $assignee->employee_id,
                    'name' => $assignee->employee?->user?->name,
                    'status' => $assignee->status,
                    'status_updated_at' => $assignee->status_updated_at,
                ]);
            }),
            'progress' => $this->when($this->relationLoaded('assignees'), function () {
                $total = $this->assignees->count();
                $done = $this->assignees->where('status', 'done')->count();

                return ['done' => $done, 'total' => $total];
            }),
            // The current viewer's own assignment on this task, when they
            // are one of the assignees -- lets the "My Tasks" list show
            // just their status without re-deriving it client-side.
            'my_assignment' => $this->when(
                $request->attributes->get('viewer_employee_id') && $this->relationLoaded('assignees'),
                function () use ($request) {
                    $mine = $this->assignees->firstWhere('employee_id', $request->attributes->get('viewer_employee_id'));

                    return $mine ? [
                        'id' => $mine->id,
                        'status' => $mine->status,
                        'status_updated_at' => $mine->status_updated_at,
                    ] : null;
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
