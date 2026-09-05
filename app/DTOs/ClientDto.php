<?php

namespace App\DTOs;

use App\Models\Client;

class ClientDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $pic_name,
        public readonly string $pic_phone,
        public readonly ?string $email = null,
        public readonly ?string $address = null,
        public readonly ?string $type = null,
        public readonly ?string $field = null,
        public readonly ?string $npwp = null,
        public readonly ?string $siup_number = null,
        public readonly ?string $nib_number = null,
        public readonly ?string $notes = null,
    ) {}

    /**
     * Convert DTO ke array (untuk create / update model)
     */
    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'pic_name'   => $this->pic_name,
            'pic_phone'  => $this->pic_phone,
            'email'      => $this->email,
            'address'    => $this->address,
            'type'       => $this->type,
            'field'      => $this->field,
            'npwp'       => $this->npwp,
            'siup_number' => $this->siup_number,
            'nib_number' => $this->nib_number,
            'notes'      => $this->notes,
        ];
    }

    /**
     * Create DTO dari request / array (CREATE)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            pic_name: $data['pic_name'],
            pic_phone: $data['pic_phone'],
            email: $data['email'] ?? null,
            address: $data['address'] ?? null,
            type: $data['type'] ?? null,
            field: $data['field'] ?? null,
            npwp: $data['npwp'] ?? null,
            siup_number: $data['siup_number'] ?? null,
            nib_number: $data['nib_number'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Create DTO untuk UPDATE (merge data lama & baru)
     */
    public static function fromArrayForUpdate(array $data, Client $client): self
    {
        return new self(
            name: $data['name'] ?? $client->name,
            pic_name: $data['pic_name'] ?? $client->pic_name,
            pic_phone: $data['pic_phone'] ?? $client->pic_phone,
            email: $data['email'] ?? $client->email,
            address: $data['address'] ?? $client->address,
            type: $data['type'] ?? $client->type,
            field: $data['field'] ?? $client->field,
            npwp: $data['npwp'] ?? $client->npwp,
            siup_number: $data['siup_number'] ?? $client->siup_number,
            nib_number: $data['nib_number'] ?? $client->nib_number,
            notes: $data['notes'] ?? $client->notes,
        );
    }
}
