<?php

namespace App\DTOs;

use App\Models\FilesCompany;

class FilesCompanyDto
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            path: $data['path'],
            name: $data['name'],
            description: $data['description'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, FilesCompany $existingFile): self
    {
        return new self(
            path: $data['path'] ?? $existingFile->path,
            name: $data['name'] ?? $existingFile->name,
            description: $data['description'] ?? $existingFile->description,
        );
    }
}

