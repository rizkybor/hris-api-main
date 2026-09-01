<?php

namespace App\DTOs;

class VendorEvaluationDto
{
    public function __construct(
        public readonly int $vendorId,
        public readonly int $rating,
        public readonly ?string $notes,
        public readonly string $evaluatedAt,
        public readonly ?int $evaluatedBy,
    ) {}

    public function toArray(): array
    {
        return [
            'vendor_id' => $this->vendorId,
            'rating' => $this->rating,
            'notes' => $this->notes,
            'evaluated_at' => $this->evaluatedAt,
            'evaluated_by' => $this->evaluatedBy,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            vendorId: (int) $data['vendor_id'],
            rating: (int) $data['rating'],
            notes: $data['notes'] ?? null,
            evaluatedAt: $data['evaluated_at'] ?? now()->format('Y-m-d'),
            evaluatedBy: isset($data['evaluated_by']) ? (int) $data['evaluated_by'] : null,
        );
    }
}
