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
        public readonly string $team_assignment_mode = 'employee',
        public readonly ?int $vendor_id = null,
        public readonly ?string $inspect_note = null,
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
            'team_assignment_mode' => $this->team_assignment_mode,
            'vendor_id' => $this->vendor_id,
            'inspect_note' => $this->inspect_note,
        ];
    }

    /**
     * Create-only: deliberately has no `inspect_note` parameter -- that
     * field can only ever be set later via fromArrayForUpdate(), by the
     * Project Leader, once the project (and its leader) actually exist.
     */
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
            team_assignment_mode: $data['team_assignment_mode'] ?? 'employee',
            vendor_id: $data['vendor_id'] ?? null,
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
            team_assignment_mode: $data['team_assignment_mode'] ?? $existingProject->team_assignment_mode,
            vendor_id: array_key_exists('vendor_id', $data) ? ($data['vendor_id'] ?: null) : $existingProject->vendor_id,
            inspect_note: array_key_exists('inspect_note', $data) ? $data['inspect_note'] : $existingProject->inspect_note,
        );
    }
}
