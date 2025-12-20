<?php

namespace App\DTOs;

use App\Models\User;

class UserDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $profile_photo = null,
        public readonly array $roles = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'profile_photo' => $this->profile_photo,
            'roles' => $this->roles,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            profile_photo: $data['profile_photo'] ?? null,
            roles: $data['roles'] ?? [],
        );
    }

    public static function fromArrayForUpdate(array $data, User $existingUser): self
    {
        return new self(
            name: $data['name'] ?? $existingUser->name,
            email: $data['email'] ?? $existingUser->email,
            password: $data['password'] ?? $existingUser->password,
            profile_photo: array_key_exists('profile_photo', $data) ? $data['profile_photo'] : $existingUser->profile_photo,
            roles: $data['roles'] ?? $existingUser->roles,
        );
    }
}
