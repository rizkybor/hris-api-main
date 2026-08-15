<?php

namespace App\DTOs;

use App\Models\ProjectDocument;

class ProjectDocumentDto
{
    public function __construct(
        public readonly int $project_id,
        public readonly string $document_name,
        public readonly string $document_path,
        public readonly ?string $type_file = null,
        public readonly ?string $size_file = null,
        public readonly ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'project_id' => $this->project_id,
            'document_name' => $this->document_name,
            'document_path' => $this->document_path,
            'type_file' => $this->type_file,
            'size_file' => $this->size_file,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            project_id: (int) $data['project_id'],
            document_name: $data['document_name'],
            document_path: $data['document_path'],
            type_file: $data['type_file'] ?? null,
            size_file: $data['size_file'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, ProjectDocument $existing): self
    {
        return new self(
            project_id: $existing->project_id,
            document_name: $data['document_name'] ?? $existing->document_name,
            document_path: $data['document_path'] ?? $existing->document_path,
            type_file: $data['type_file'] ?? $existing->type_file,
            size_file: $data['size_file'] ?? $existing->size_file,
            description: $data['description'] ?? $existing->description,
        );
    }
}
