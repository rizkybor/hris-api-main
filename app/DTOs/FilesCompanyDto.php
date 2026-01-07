<?php

namespace App\DTOs;

use App\Models\FilesCompany;

class FilesCompanyDto
{
    public function __construct(
        public readonly string $document_name,
        public readonly string $document_path,
        public readonly ?string $type_file = null,
        public readonly ?int $size_file = null, // ubah jadi int
        public readonly ?string $description = null,
    ) {}

    /**
     * Convert DTO ke array (untuk create / update model)
     */
    public function toArray(): array
    {
        return [
            'document_name' => $this->document_name,
            'document_path' => $this->document_path,
            'type_file'     => $this->type_file,
            'size_file'     => $this->size_file,
            'description'   => $this->description,
        ];
    }

    /**
     * Create DTO dari array (CREATE)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            document_name: $data['document_name'],
            document_path: $data['document_path'],
            type_file: $data['type_file'] ?? null,
            size_file: isset($data['size_file']) ? (int)$data['size_file'] : null,
            description: $data['description'] ?? null,
        );
    }

    /**
     * Create DTO untuk UPDATE (merge data lama & baru)
     */
    public static function fromArrayForUpdate(array $data, FilesCompany $existingFile): self
    {
        return new self(
            document_name: $data['document_name'] ?? $existingFile->document_name,
            document_path: $data['document_path'] ?? $existingFile->document_path,
            type_file: $data['type_file'] ?? $existingFile->type_file,
            size_file: isset($data['size_file']) ? (int)$data['size_file'] : $existingFile->size_file,
            description: $data['description'] ?? $existingFile->description,
        );
    }
}
