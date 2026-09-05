<?php

namespace App\DTOs;

use App\Models\ClientTaskScope;

class ClientTaskScopeDto
{
    public function __construct(
        public readonly string $name,
    ) {}

    /**
     * Convert DTO ke array (untuk create / update)
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    /**
     * Create DTO dari array (CREATE)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
        );
    }

    /**
     * Create DTO untuk UPDATE (merge data lama & baru)
     */
    public static function fromArrayForUpdate(array $data, ClientTaskScope $scope): self
    {
        return new self(
            name: $data['name'] ?? $scope->name,
        );
    }
}
