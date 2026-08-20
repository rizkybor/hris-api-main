<?php

namespace App\DTOs;

use App\Models\ProjectTask;

class ProjectTaskDto
{
    public function __construct(
        public readonly int $project_id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?int $assignee_id,
        public readonly string $priority,
        public readonly string $status,
        public readonly ?string $due_date = null,
        public readonly ?string $type = null,
        public readonly ?string $color = null,
    ) {}

    public function toArray(): array
    {
        return [
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'assignee_id' => $this->assignee_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'type' => $this->type,
            'color' => $this->color,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            project_id: $data['project_id'],
            name: $data['name'],
            description: $data['description'] ?? null,
            assignee_id: $data['assignee_id'] ?? null,
            priority: $data['priority'],
            status: $data['status'],
            due_date: $data['due_date'] ?? null,
            type: $data['type'] ?? null,
            color: $data['color'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, ProjectTask $existingTask): self
    {
        return new self(
            project_id: $data['project_id'] ?? $existingTask->project_id,
            name: $data['name'] ?? $existingTask->name,
            description: $data['description'] ?? $existingTask->description,
            assignee_id: $data['assignee_id'] ?? $existingTask->assignee_id,
            priority: $data['priority'] ?? $existingTask->priority,
            status: $data['status'] ?? $existingTask->status,
            due_date: $data['due_date'] ?? ($existingTask->due_date ? $existingTask->due_date->format('Y-m-d') : null),
            type: array_key_exists('type', $data) ? $data['type'] : $existingTask->type,
            color: array_key_exists('color', $data) ? $data['color'] : $existingTask->color,
        );
    }
}
