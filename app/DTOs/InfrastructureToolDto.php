<?php

namespace App\DTOs;

use App\Models\InfrastructureTool;

class InfrastructureToolDTO
{
    public function __construct(
        public readonly string $tech_stack_component,
        public readonly string $vendor,
        public readonly ?float $monthly_fee = null,
        public readonly ?float $annual_fee = null,
        public readonly string $expired_date,
        public readonly string $status,
        public readonly ?string $notes = null,
    )
    {}

    public function toArray(): array
    {
        return [
            'tech_stack_component' => $this->tech_stack_component,
            'vendor' => $this->vendor,
            'monthly_fee' => $this->monthly_fee,
            'annual_fee' => $this->annual_fee,
            'expired_date' => $this->expired_date,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tech_stack_component: $data['tech_stack_component'],
            vendor: $data['vendor'],
            monthly_fee: isset($data['monthly_fee']) ? (float) $data['monthly_fee'] : null,
            annual_fee: isset($data['annual_fee']) ? (float) $data['annual_fee'] : null,
            expired_date: $data['expired_date'],
            status: $data['status'],
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, InfrastructureTool $existingInfrastructureTool): self
    {
        return new self(
            tech_stack_component: $data['tech_stack_component'] ?? $existingInfrastructureTool->tech_stack_component,
            vendor: $data['vendor'] ?? $existingInfrastructureTool->vendor,
            monthly_fee: isset($data['monthly_fee']) ? (float) $data['monthly_fee'] : $existingInfrastructureTool->monthly_fee,
            annual_fee: isset($data['annual_fee']) ? (float) $data['annual_fee'] : $existingInfrastructureTool->annual_fee,
            expired_date: $data['expired_date'] ?? $existingInfrastructureTool->expired_date,
            status: $data['status'] ?? $existingInfrastructureTool->status,
            notes: $data['notes'] ?? $existingInfrastructureTool->notes,
        );
    }
}