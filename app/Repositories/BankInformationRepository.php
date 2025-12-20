<?php

namespace App\Repositories;

use App\Interfaces\BankInformationRepositoryInterface;
use App\Models\BankInformation;

class BankInformationRepository implements BankInformationRepositoryInterface
{
    public function getById(string $id): BankInformation
    {
        return BankInformation::with(['employee'])->findOrFail($id);
    }

    public function create(array $data): BankInformation
    {
        return BankInformation::create($data);
    }

    public function update(string $id, array $data): BankInformation
    {
        $bankInfo = $this->getById($id);
        $bankInfo->update($data);

        return $bankInfo;
    }

    public function delete(string $id): BankInformation
    {
        $bankInfo = $this->getById($id);
        $bankInfo->delete();

        return $bankInfo;
    }
}
