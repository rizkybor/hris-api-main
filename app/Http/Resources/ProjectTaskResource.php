<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'assignee_id' => $this->assignee_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assignee' => new EmployeeProfileResource($this->whenLoaded('assignee')),
            'can_comment' => $this->when($request->user(), function () use ($request) {
                $user = $request->user();
                if ($user->hasRole('manager')) {
                    return true;
                }

                $employeeId = $user->employeeProfile?->id;

                return $employeeId && $this->project && $this->project->isEmployeeProjectParticipant($employeeId);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
