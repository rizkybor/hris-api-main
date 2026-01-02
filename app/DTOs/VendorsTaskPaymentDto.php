<?php

namespace App\DTOs;

use App\Models\VendorsTaskPayment;

class VendorsTaskPaymentDto
{
    public function __construct(
        public readonly string $document_name,
        public readonly string $document_path,
        public readonly ?float $amount = null,
        public readonly ?string $payment_date = null
    ) {}

    /**
     * Convert DTO ke array (untuk create/update model)
     */
    public function toArray(): array
    {
        return [
            'document_name'   => $this->document_name,
            'document_path'   => $this->document_path,
            'amount'          => $this->amount,
            'payment_date'    => $this->payment_date
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
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            payment_date: $data['payment_date'] ?? null
        );
    }

    /**
     * Create DTO untuk UPDATE (merge data lama & baru)
     */
    public static function fromArrayForUpdate(array $data, VendorsTaskPayment $payment): self
    {
        return new self(
            document_name: $data['document_name'] ?? $payment->document_name,
            document_path: $data['document_path'] ?? $payment->document_path,
            amount: isset($data['amount']) ? (float) $data['amount'] : $payment->amount,
            payment_date: $data['payment_date'] ?? $payment->payment_date?->format('Y-m-d')
        );
    }
}
