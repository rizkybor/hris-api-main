<?php

namespace App\DTOs;

use App\Models\FixedCost;

class FixedCostDto
{
    public function __construct(
        public readonly string $financial_items,
        public readonly string $description,
        public readonly ?float $actual = null,
        public readonly ?string $notes = null,
        public readonly string $status = 'active',
        public readonly ?string $expired_date = null,
    ) {}

    public function toArray(): array
    {
        return [
            'financial_items' => $this->financial_items,
            'description' => $this->description,
            'actual' => $this->actual,
            'notes' => $this->notes,
            'status' => $this->status,
            'expired_date' => $this->expired_date,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            financial_items: $data['financial_items'],
            description: $data['description'],
            actual: isset($data['actual']) ? (float) $data['actual'] : null,
            notes: $data['notes'] ?? null,
            status: $data['status'] ?? 'active',
            expired_date: $data['expired_date'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, FixedCost $existingFixedCost): self
    {
        return new self(
            financial_items: $data['financial_items'] ?? $existingFixedCost->financial_items,
            description: $data['description'] ?? $existingFixedCost->description,
            actual: isset($data['actual']) ? (float) $data['actual'] : $existingFixedCost->actual,
            notes: $data['notes'] ?? $existingFixedCost->notes,
            status: $data['status'] ?? $existingFixedCost->status,
            // array_key_exists: an explicit null clears the date (an item no
            // longer in use doesn't need one); a missing key keeps it.
            expired_date: array_key_exists('expired_date', $data) ? $data['expired_date'] : $existingFixedCost->expired_date?->toDateString(),
        );
    }
}
