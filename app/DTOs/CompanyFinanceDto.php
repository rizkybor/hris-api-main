<?php

namespace App\DTOs;

class CompanyFinanceDTO
{
    public float $saldo_company;

    public function __construct(array $data)
    {
        $this->saldo_company = floatval($data['saldo_company']);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromArrayForUpdate(array $data, $model): self
    {
        $merged = array_merge($model->toArray(), $data);
        return new self($merged);
    }

    public function toArray(): array
    {
        return [
            'saldo_company' => $this->saldo_company
        ];
    }
}