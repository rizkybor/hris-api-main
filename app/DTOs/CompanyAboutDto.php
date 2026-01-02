<?php

namespace App\DTOs;

use App\Models\CompanyAbout;

class CompanyAboutDto
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $vision = null,
        public readonly ?array $mission = [],
        public readonly ?string $established_date = null,
        public readonly ?array $branches = [],
        public readonly ?string $address = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'vision' => $this->vision,
            'mission' => $this->mission ? json_encode($this->mission) : null,
            'established_date' => $this->established_date,
            'branches' => $this->branches ? json_encode($this->branches) : null,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            vision: $data['vision'] ?? null,
            mission: isset($data['mission']) ? (array)$data['mission'] : null,
            established_date: $data['established_date'] ?? null,
            branches: isset($data['branches']) ? (array)$data['branches'] : null,
            address: $data['address'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, CompanyAbout $existing): self
    {
        return new self(
            name: $data['name'] ?? $existing->name,
            description: $data['description'] ?? $existing->description,
            vision: $data['vision'] ?? $existing->vision,
            mission:  $data['mission'] ?? ($existing->mission ? json_decode($existing->mission, true) : null),
            established_date: $data['established_date'] ?? $existing->established_date,
            branches: $data['branches'] ?? ($existing->branches ? json_decode($existing->branches, true) : null),
            address: $data['address'] ?? $existing->address,
            email: $data['email'] ?? $existing->email,
            phone: $data['phone'] ?? $existing->phone,
        );
    }
}
