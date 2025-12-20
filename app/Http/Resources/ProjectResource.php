<?php

namespace App\Http\Resources;

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
            'photo' => $this->photo ? asset('storage/'.$this->photo) : null,
            'budget' => (float) (string) $this->budget,
            'progress' => $progress,
            'leader' => new EmployeeProfileResource($this->projectLeader),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
