<?php

namespace App\DTOs;

use App\Models\EmergencyContact;

class EmergencyContactDto
{
    public function __construct(
        public readonly int $employee_id,
        public readonly string $full_name,
        public readonly string $relationship,
        public readonly string $phone,
        public readonly ?string $email = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            employee_id: $data['employee_id'],
            full_name: $data['full_name'],
            relationship: $data['relationship'],
            phone: $data['phone'],
            email: $data['email'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, EmergencyContact $existingContact): self
    {
        return new self(
            employee_id: $data['employee_id'] ?? $existingContact->employee_id,
            full_name: $data['full_name'] ?? $existingContact->full_name,
            relationship: $data['relationship'] ?? $existingContact->relationship,
            phone: $data['phone'] ?? $existingContact->phone,
            email: $data['email'] ?? $existingContact->email,
        );
    }
}
