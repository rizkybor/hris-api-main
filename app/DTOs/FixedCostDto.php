<?php

namespace App\DTOs;

use App\Models\FixedCost;

class FixedCostDto
{
    public function __construct(
        public readonly string $financial_items,
        public readonly string $description,
        public readonly ?float $budget = null,
        public readonly ?float $actual = null,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'financial_items' => $this->financial_items,
            'description' => $this->description,
            'budget' => $this->budget,
            'actual' => $this->actual,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            financial_items: $data['financial_items'],
            description: $data['description'],
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            actual: isset($data['actual']) ? (float) $data['actual'] : null,
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, FixedCost $existingFixedCost): self
    {
        return new self(
            financial_items: $data['financial_items'] ?? $existingFixedCost->financial_items,
            description: $data['description'] ?? $existingFixedCost->description,
            budget: isset($data['budget']) ? (float) $data['budget'] : $existingFixedCost->budget,
            actual: isset($data['actual']) ? (float) $data['actual'] : $existingFixedCost->actual,
            notes: $data['notes'] ?? $existingFixedCost->notes,
        );
    }
}
