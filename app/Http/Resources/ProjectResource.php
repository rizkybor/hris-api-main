<?php

namespace App\Http\Resources;

use App\Services\Cloudinary\CloudinaryUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate progress based on tasks completion
        $progress = 0;
        if ($this->relationLoaded('tasks') && $this->tasks->count() > 0) {
            $completedTasks = $this->tasks->where('status', 'done')->count();
            $progress = round(($completedTasks / $this->tasks->count()) * 100);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'photo' => CloudinaryUrl::image($this->photo),
            'budget' => (float) (string) $this->budget,
            'progress' => $progress,
            'leader' => new EmployeeProfileResource($this->projectLeader),
            'team_assignment_mode' => $this->team_assignment_mode,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
