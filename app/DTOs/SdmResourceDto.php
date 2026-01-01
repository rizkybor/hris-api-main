<?php

namespace App\DTOs;

use App\Models\SdmResource;

class SdmResourceDTO
{

    public function __construct(
        public readonly string $sdm_component,
        public readonly string $metrik,
        public readonly string $capacity_target,
        public readonly ?float $budget = null,
        public readonly ?float $actual = null,
        public readonly string $rag_status,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'sdm_component' => $this->sdm_component,
            'metrik' => $this->metrik,
            'capacity_target' => $this->capacity_target,
            'budget' => $this->budget,
            'actual' => $this->actual,
            'rag_status' => $this->rag_status,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sdm_component: $data['sdm_component'],
            metrik: $data['metrik'],
            capacity_target: $data['capacity_target'],
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            actual: isset($data['actual']) ? (float) $data['actual'] : null,
            rag_status: $data['rag_status'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, SdmResource $existingSdmResource): self
    {
        return new self(
            sdm_component: $data['sdm_component'] ?? $existingSdmResource->sdm_component,
            metrik: $data['metrik'] ?? $existingSdmResource->metrik,
            capacity_target: $data['capacity_target'] ?? $existingSdmResource->capacity_target,
            budget: isset($data['budget']) ? (float) $data['budget'] : $existingSdmResource->budget,
            actual: isset($data['actual']) ? (float) $data['actual'] : $existingSdmResource->actual,
            rag_status: $data['rag_status'] ?? $existingSdmResource->rag_status,
            notes: $data['notes'] ?? $existingSdmResource->notes,
        );
    }
}
