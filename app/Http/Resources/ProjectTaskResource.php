<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
{
    /**
     * Participant employee-id sets keyed by project id, computed at most
     * once per project per request instead of re-querying per task row.
     *
     * @var array<int, \Illuminate\Support\Collection<int, int>>
     */
    private static array $participantIdsByProject = [];

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
            'image' => CloudinaryUrl::image($this->image),
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
                if (! $employeeId) {
                    return false;
                }

                // assignee_id isn't restricted to the project's own team
                // roster, so the assignee is checked separately from
                // isEmployeeProjectParticipant -- otherwise someone assigned
                // to a task outside their team couldn't comment on it.
                $isAssignee = $this->assignee_id === $employeeId;
                $isParticipant = $this->project && $this->getParticipantIds($this->project)->contains($employeeId);

                return $isAssignee || $isParticipant;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getParticipantIds($project): \Illuminate\Support\Collection
    {
        return self::$participantIdsByProject[$project->id] ??= $project->getParticipantEmployeeIds();
    }
}
