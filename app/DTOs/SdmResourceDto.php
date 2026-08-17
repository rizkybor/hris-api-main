<?php

namespace App\DTOs;

use App\Models\SdmField;
use App\Models\SdmResource;

class SdmResourceDto
{

    public function __construct(
        public readonly string $sdm_component,
        public readonly string $rag_status,
        public readonly ?int $sdm_field_id = null,
        public readonly ?float $productive_hours_per_month = null,
        public readonly ?string $metrik = null,
        public readonly ?string $capacity_target = null,
        public readonly ?float $budget = null,
        public readonly ?float $actual = null,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'sdm_field_id' => $this->sdm_field_id,
            'productive_hours_per_month' => $this->productive_hours_per_month,
            'sdm_component' => $this->sdm_component,
            'metrik' => $this->metrik,
            'capacity_target' => $this->capacity_target,
            'budget' => $this->budget,
            'actual' => $this->actual,
            'rag_status' => $this->rag_status,
            'notes' => $this->notes,
        ];
    }

    /**
     * sdm_component (the display label) is derived from the selected
     * Bidang's name when one isn't explicitly provided, since the Bidang
     * dropdown replaced free-typing a component name.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sdm_field_id: isset($data['sdm_field_id']) ? (int) $data['sdm_field_id'] : null,
            productive_hours_per_month: isset($data['productive_hours_per_month']) ? (float) $data['productive_hours_per_month'] : null,
            sdm_component: $data['sdm_component'] ?? self::resolveComponentName($data['sdm_field_id'] ?? null),
            metrik: $data['metrik'] ?? null,
            capacity_target: $data['capacity_target'] ?? null,
            budget: isset($data['budget']) ? (float) $data['budget'] : null,
            actual: isset($data['actual']) ? (float) $data['actual'] : null,
            rag_status: $data['rag_status'],
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, SdmResource $existingSdmResource): self
    {
        $fieldId = isset($data['sdm_field_id']) ? (int) $data['sdm_field_id'] : $existingSdmResource->sdm_field_id;

        return new self(
            sdm_field_id: $fieldId,
            productive_hours_per_month: isset($data['productive_hours_per_month']) ? (float) $data['productive_hours_per_month'] : $existingSdmResource->productive_hours_per_month,
            sdm_component: $data['sdm_component'] ?? self::resolveComponentName($fieldId) ?? $existingSdmResource->sdm_component,
            metrik: $data['metrik'] ?? $existingSdmResource->metrik,
            capacity_target: $data['capacity_target'] ?? $existingSdmResource->capacity_target,
            budget: isset($data['budget']) ? (float) $data['budget'] : $existingSdmResource->budget,
            actual: isset($data['actual']) ? (float) $data['actual'] : $existingSdmResource->actual,
            rag_status: $data['rag_status'] ?? $existingSdmResource->rag_status,
            notes: $data['notes'] ?? $existingSdmResource->notes,
        );
    }

    private static function resolveComponentName(?int $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        return SdmField::find($fieldId)?->name;
    }
}
