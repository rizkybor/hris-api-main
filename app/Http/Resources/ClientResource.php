<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'name' => $this->name,
            'pic_name' => $this->pic_name,
            'pic_phone' => $this->pic_phone,
            'email' => $this->email,
            'address' => $this->address,
            'type' => $this->type,
            'field' => $this->field,
            'npwp' => $this->npwp,
            'siup_number' => $this->siup_number,
            'nib_number' => $this->nib_number,
            'notes' => $this->notes,
            // Cheap aggregates from the query's withAvg/withCount -- both
            // read as plain null when a client has zero evaluations (an
            // isset() check here would wrongly treat that null as "wasn't
            // loaded" and drop the key), and null when the caller's query
            // didn't eager-load them at all, which is an equally fine
            // fallback since there's nothing to report either way.
            'average_rating' => $this->evaluations_avg_rating !== null ? round((float) $this->evaluations_avg_rating, 2) : null,
            'evaluations_count' => (int) ($this->evaluations_count ?? 0),
            'evaluations' => $this->whenLoaded('evaluations', function () {
                return ClientEvaluationResource::collection($this->evaluations);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relasi task pivots
            'task_pivots' => $this->whenLoaded('taskPivots', function () {
                return ClientTaskPivotResource::collection($this->taskPivots);
            }),

            // Relasi attachments
            'attachments' => $this->whenLoaded('attachments', function () {
                return ClientAttachmentResource::collection($this->attachments);
            }),

            // Projects this client is contracted on -- optional, may be empty.
            'projects' => $this->whenLoaded('projects', function () {
                return $this->projects->map(fn ($project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                ]);
            }),
        ];
    }
}
