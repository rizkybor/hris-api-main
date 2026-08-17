<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskCommentResource extends JsonResource
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
            'project_task_id' => $this->project_task_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'user' => new UserResource($this->whenLoaded('user')),
            'mentioned_employees' => EmployeeProfileResource::collection($this->mentionedEmployees()),
            'reply_to' => $this->when($this->parent_id && $this->relationLoaded('parent') && $this->parent, function () {
                return [
                    'id' => $this->parent->id,
                    'user_name' => $this->parent->user?->name,
                    'snippet' => \Illuminate\Support\Str::limit($this->parent->body, 60),
                ];
            }),
            'can_delete' => $this->when($request->user(), function () use ($request) {
                $user = $request->user();
                if ($user->hasRole('manager')) {
                    return true;
                }

                $employeeId = $user->employeeProfile?->id;

                return $employeeId && $this->task?->project && $this->task->project->isProjectLeader($employeeId);
            }),
            'created_at' => $this->created_at,
        ];
    }
}
