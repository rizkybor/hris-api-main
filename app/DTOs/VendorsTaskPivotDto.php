<?php

namespace App\DTOs;

use App\Models\VendorsTaskPivot;

class VendorsTaskPivotDto
{
    public function __construct(
        public readonly int $vendor_id,
        public readonly int $scope_vendor_id,
        public readonly int $task_vendor_id,
        public readonly ?int $task_payment_id = null,
        public readonly bool $maintenance = false,
        public readonly ?float $contract_value = null,
        public readonly ?string $contract_status = null,
        public readonly ?string $contract_start = null, // format Y-m-d
        public readonly ?string $contract_end = null,   // format Y-m-d
    ) {}

    /**
     * Convert DTO ke array (untuk create/update)
     */
    public function toArray(): array
    {
        return [
            'vendor_id'       => $this->vendor_id,
            'scope_vendor_id' => $this->scope_vendor_id,
            'task_vendor_id'  => $this->task_vendor_id,
            'task_payment_id' => $this->task_payment_id,
            'maintenance'     => $this->maintenance,
            'contract_value'  => $this->contract_value,
            'contract_status' => $this->contract_status,
            'contract_start'  => $this->contract_start,
            'contract_end'    => $this->contract_end,
        ];
    }

    /**
     * Create DTO dari array (CREATE)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vendor_id: $data['vendor_id'],
            scope_vendor_id: $data['scope_vendor_id'],
            task_vendor_id: $data['task_vendor_id'],
            task_payment_id: $data['task_payment_id'] ?? null,
            maintenance: $data['maintenance'] ?? false,
            contract_value: isset($data['contract_value']) ? (float) $data['contract_value'] : null,
            contract_status: $data['contract_status'] ?? null,
            contract_start: $data['contract_start'] ?? null,
            contract_end: $data['contract_end'] ?? null,
        );
    }

    /**
     * Create DTO untuk UPDATE (merge data lama & baru)
     */
    public static function fromArrayForUpdate(array $data, VendorsTaskPivot $pivot): self
    {
        return new self(
            vendor_id: $data['vendor_id'] ?? $pivot->vendor_id,
            scope_vendor_id: $data['scope_vendor_id'] ?? $pivot->scope_vendor_id,
            task_vendor_id: $data['task_vendor_id'] ?? $pivot->task_vendor_id,
            task_payment_id: $data['task_payment_id'] ?? $pivot->task_payment_id,
            maintenance: $data['maintenance'] ?? $pivot->maintenance,
            contract_value: isset($data['contract_value']) ? (float) $data['contract_value'] : $pivot->contract_value,
            contract_status: $data['contract_status'] ?? $pivot->contract_status,
            contract_start: $data['contract_start'] ?? $pivot->contract_start?->format('Y-m-d'),
            contract_end: $data['contract_end'] ?? $pivot->contract_end?->format('Y-m-d'),
        );
    }
}
