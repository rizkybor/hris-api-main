<?php

namespace App\DTOs;

use App\Models\Team;

class TeamDto
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $expected_size,
        public readonly ?string $description,
        public readonly ?string $icon,
        public readonly string $department,
        public readonly string $status = 'active',
        public readonly ?string $team_lead_id = null,
        public readonly array $responsibilities = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'expected_size' => $this->expected_size,
            'description' => $this->description,
            'icon' => $this->icon,
            'department' => $this->department,
            'status' => $this->status,
            'team_lead_id' => $this->team_lead_id,
            'responsibilities' => $this->responsibilities,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            expected_size: $data['expected_size'] ?? null,
            description: $data['description'] ?? null,
            icon: $data['icon'] ?? null,
            department: $data['department'],
            status: $data['status'] ?? 'active',
            team_lead_id: $data['team_lead_id'] ?? null,
            responsibilities: $data['responsibilities'] ?? [],
        );
    }

    public static function fromArrayForUpdate(array $data, Team $existingTeam): self
    {
        return new self(
            name: $data['name'] ?? $existingTeam->name,
            expected_size: $data['expected_size'] ?? $existingTeam->expected_size,
            description: $data['description'] ?? $existingTeam->description,
            icon: $data['icon'] ?? $existingTeam->icon,
            department: $data['department'] ?? $existingTeam->department->value,
            status: $data['status'] ?? $existingTeam->status->value,
            team_lead_id: $data['team_lead_id'] ?? $existingTeam->team_lead_id,
            responsibilities: $data['responsibilities'] ?? $existingTeam->responsibilities,
        );
    }
}
