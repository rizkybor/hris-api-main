<?php

namespace App\Repositories;

use App\DTOs\CompanyFinanceDTO;
use App\Interfaces\CompanyFinanceRepositoryInterface;
use App\Models\CompanyFinance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CompanyFinanceRepository implements CompanyFinanceRepositoryInterface
{
    public function getAll(
        ?string $search = null,
        ?int $limit = null,
        bool $execute = true
    ): Builder|Collection {
        $query = CompanyFinance::query()
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search); // Asumsikan scope search ada di model
                }
            })
            ->orderByDesc('created_at');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }


    public function getById(
        string $id
    ): CompanyFinance {
        return CompanyFinance::findOrFail($id);
    }

    public function create(
        array $data
    ): CompanyFinance {
        $dto = CompanyFinanceDTO::fromArray($data);
        $array = $dto->toArray();

        return CompanyFinance::create($array);
    }

    public function getStatistic(?string $search = null): array
    {
        $query = CompanyFinance::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('saldo_company', 'like', '%' . $search . '%');
                // tambahkan kolom lain jika perlu
            });
        }

        $items = $query->get();

        $totalSaldo = $items->sum('saldo_company');

        return [
            'items' => $items,
            'summary' => [
                'total_saldo_company' => $totalSaldo,
            ],
        ];
    }


    public function update(
        string $id,
        array $data
    ): CompanyFinance {
        $finance = $this->getById($id);
        $dto = CompanyFinanceDTO::fromArrayForUpdate($data, $finance);
        $finance->update($dto->toArray());

        return $finance;
    }

    public function delete(
        string $id
    ): CompanyFinance {
        $finance = $this->getById($id);
        $finance->delete();

        return $finance;
    }

    public function first(): ?CompanyFinance
    {
        return CompanyFinance::first();
    }

    public function getLatestBalance(): float
    {
        $finance = CompanyFinance::latest()->first();

        return $finance ? (float) $finance->saldo_company : 0;
    }
}
