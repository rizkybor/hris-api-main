<?php

namespace App\DTOs;

use App\Models\Project;

class ProjectDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $priority,
        public readonly string $status,
        public readonly string $start_date,
        public readonly ?string $end_date = null,
        public readonly ?string $description = null,
        public readonly ?string $photo = null,
        public readonly ?float $budget = null,
        public readonly ?int $project_leader_id = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'description' => $this->description,
            'photo' => $this->photo,
            'budget' => $this->budget,
            'project_leader_id' => $this->project_leader_id,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            priority: $data['priority'],
            status: $data['status'],
            start_date: $data['start_date'],
            end_date: $data['end_date'] ?? null,
            description: $data['description'] ?? null,
            photo: $data['photo'] ?? null,
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            project_leader_id: $data['project_leader_id'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, Project $existingProject): self
    {
        return new self(
            name: $data['name'] ?? $existingProject->name,
            type: $data['type'] ?? $existingProject->type,
            priority: $data['priority'] ?? $existingProject->priority,
            status: $data['status'] ?? $existingProject->status,
            start_date: $data['start_date'] ?? ($existingProject->start_date ? $existingProject->start_date : null),
            end_date: $data['end_date'] ?? ($existingProject->end_date ? $existingProject->end_date : null),
            description: $data['description'] ?? $existingProject->description,
            photo: $data['photo'] ?? $existingProject->photo,
            budget: isset($data['budget']) ? (float) $data['budget'] : $existingProject->budget,
            project_leader_id: $data['project_leader_id'] ?? $existingProject->project_leader_id,
        );
    }
}
