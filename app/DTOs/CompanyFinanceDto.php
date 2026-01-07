<?php

namespace App\DTOs;

use App\Models\CompanyFinance;

class CompanyFinanceDto
{

    public function __construct(
        public readonly ?float $saldo_company
    )
    {}

    public function toArray(): array
    {
        return [
            'saldo_company' => $this->saldo_company
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            saldo_company: isset($data['saldo_company']) ? (float) $data['saldo_company'] : null,
        );
    }

    public static function fromArrayForUpdate(array $data, CompanyFinance $existingCompanyFinance): self
    {
        return new self(
            saldo_company: isset($data['saldo_company']) ? (float) $data['saldo_company'] : $existingCompanyFinance->saldo_company,
        );
    }
}