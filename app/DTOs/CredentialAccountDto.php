<?php

namespace App\DTOs;

use App\Models\CredentialAccount;

class CredentialAccountDto
{
    public function __construct(
        public readonly string $label_password,
        public readonly string $username_email,
        public readonly string $password,
        public readonly ?string $website = null,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'label_password' => $this->label_password,
            'username_email' => $this->username_email,
            'password' => $this->password,
            'website' => $this->website,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            label_password: $data['label_password'],
            username_email: $data['username_email'],
            password: $data['password'],
            website: $data['website'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, CredentialAccount $existingAccount): self
    {
        return new self(
            label_password: $data['label_password'] ?? $existingAccount->label_password,
            username_email: $data['username_email'] ?? $existingAccount->username_email,
            password: $data['password'] ?? $existingAccount->password,
            website: $data['website'] ?? $existingAccount->website,
            notes: $data['notes'] ?? $existingAccount->notes,
        );
    }
}

