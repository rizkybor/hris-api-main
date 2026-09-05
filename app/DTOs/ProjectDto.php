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
        public readonly ?int $warranty_period_months = null,
        public readonly ?int $retention_period_months = null,
        public readonly ?int $project_leader_id = null,
        public readonly string $team_assignment_mode = 'employee',
        public readonly ?int $client_id = null,
        public readonly ?string $inspect_note = null,
        public readonly ?string $access_project_name = null,
        public readonly ?string $access_project_url = null,
        public readonly ?string $access_github_name = null,
        public readonly ?string $access_github_url = null,
        public readonly ?string $access_figma_name = null,
        public readonly ?string $access_figma_url = null,
        public readonly ?array $additional_access = null,
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
            'warranty_period_months' => $this->warranty_period_months,
            'retention_period_months' => $this->retention_period_months,
            'project_leader_id' => $this->project_leader_id,
            'team_assignment_mode' => $this->team_assignment_mode,
            'client_id' => $this->client_id,
            'inspect_note' => $this->inspect_note,
            'access_project_name' => $this->access_project_name,
            'access_project_url' => $this->access_project_url,
            'access_github_name' => $this->access_github_name,
            'access_github_url' => $this->access_github_url,
            'access_figma_name' => $this->access_figma_name,
            'access_figma_url' => $this->access_figma_url,
            'additional_access' => $this->additional_access,
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
            warranty_period_months: isset($data['warranty_period_months']) ? (int) $data['warranty_period_months'] : null,
            retention_period_months: isset($data['retention_period_months']) ? (int) $data['retention_period_months'] : null,
            project_leader_id: $data['project_leader_id'] ?? null,
            team_assignment_mode: $data['team_assignment_mode'] ?? 'employee',
            client_id: $data['client_id'] ?? null,
            access_project_name: $data['access_project_name'] ?? null,
            access_project_url: $data['access_project_url'] ?? null,
            access_github_name: $data['access_github_name'] ?? null,
            access_github_url: $data['access_github_url'] ?? null,
            access_figma_name: $data['access_figma_name'] ?? null,
            access_figma_url: $data['access_figma_url'] ?? null,
            additional_access: $data['additional_access'] ?? null,
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
            warranty_period_months: array_key_exists('warranty_period_months', $data) ? ($data['warranty_period_months'] !== null ? (int) $data['warranty_period_months'] : null) : $existingProject->warranty_period_months,
            retention_period_months: array_key_exists('retention_period_months', $data) ? ($data['retention_period_months'] !== null ? (int) $data['retention_period_months'] : null) : $existingProject->retention_period_months,
            project_leader_id: $data['project_leader_id'] ?? $existingProject->project_leader_id,
            team_assignment_mode: $data['team_assignment_mode'] ?? $existingProject->team_assignment_mode,
            client_id: array_key_exists('client_id', $data) ? ($data['client_id'] ?: null) : $existingProject->client_id,
            inspect_note: array_key_exists('inspect_note', $data) ? $data['inspect_note'] : $existingProject->inspect_note,
            access_project_name: array_key_exists('access_project_name', $data) ? $data['access_project_name'] : $existingProject->access_project_name,
            access_project_url: array_key_exists('access_project_url', $data) ? $data['access_project_url'] : $existingProject->access_project_url,
            access_github_name: array_key_exists('access_github_name', $data) ? $data['access_github_name'] : $existingProject->access_github_name,
            access_github_url: array_key_exists('access_github_url', $data) ? $data['access_github_url'] : $existingProject->access_github_url,
            access_figma_name: array_key_exists('access_figma_name', $data) ? $data['access_figma_name'] : $existingProject->access_figma_name,
            access_figma_url: array_key_exists('access_figma_url', $data) ? $data['access_figma_url'] : $existingProject->access_figma_url,
            additional_access: array_key_exists('additional_access', $data) ? $data['additional_access'] : $existingProject->additional_access,
        );
    }
}
