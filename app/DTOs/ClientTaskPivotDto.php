<?php

namespace App\DTOs;

use App\Models\ClientTaskPivot;

class ClientTaskPivotDto
{
    public function __construct(
        public readonly int $client_id,
        public readonly ?int $scope_client_id = null,
        public readonly ?int $task_client_id = null,
        public readonly ?int $payment_client_id = null,
        public readonly bool $maintenance = false,
        public readonly ?float $contract_value = null,
        public readonly ?string $contract_status = null,
        public readonly ?string $contract_start = null,
        public readonly ?string $contract_end = null,
    ) {}

    /**
     * Convert DTO ke array (untuk create/update)
     */
    public function toArray(): array
    {
        return [
            'client_id'       => $this->client_id,
            'scope_client_id' => $this->scope_client_id,
            'task_client_id'  => $this->task_client_id,
            'payment_client_id' => $this->payment_client_id,
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
            client_id: $data['client_id'],
            scope_client_id: $data['scope_client_id'] ?? null,
            task_client_id: $data['task_client_id'] ?? null,
            payment_client_id: $data['payment_client_id'] ?? null,
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
    public static function fromArrayForUpdate(array $data, ClientTaskPivot $pivot): self
    {
        return new self(
            client_id: $data['client_id'] ?? $pivot->client_id,
            scope_client_id: $data['scope_client_id'] ?? $pivot->scope_client_id,
            task_client_id: $data['task_client_id'] ?? $pivot->task_client_id,
            payment_client_id: $data['payment_client_id'] ?? $pivot->payment_client_id,
            maintenance: $data['maintenance'] ?? $pivot->maintenance,
            contract_value: isset($data['contract_value']) ? (float) $data['contract_value'] : $pivot->contract_value,
            contract_status: $data['contract_status'] ?? $pivot->contract_status,
            contract_start: $data['contract_start'] ?? $pivot->contract_start?->format('Y-m-d'),
            contract_end: $data['contract_end'] ?? $pivot->contract_end?->format('Y-m-d'),
        );
    }
}
