<?php

namespace App\DTOs;

use App\Models\Vendors;

class VendorsDto
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
    public static function fromArrayForUpdate(array $data, Vendors $vendor): self
    {
        return new self(
            name: $data['name'] ?? $vendor->name,
            pic_name: $data['pic_name'] ?? $vendor->pic_name,
            pic_phone: $data['pic_phone'] ?? $vendor->pic_phone,
            email: $data['email'] ?? $vendor->email,
            address: $data['address'] ?? $vendor->address,
            type: $data['type'] ?? $vendor->type,
            field: $data['field'] ?? $vendor->field,
            npwp: $data['npwp'] ?? $vendor->npwp,
            siup_number: $data['siup_number'] ?? $vendor->siup_number,
            nib_number: $data['nib_number'] ?? $vendor->nib_number,
            notes: $data['notes'] ?? $vendor->notes,
        );
    }
}
