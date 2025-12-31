<?php

namespace App\Repositories;

use App\DTOs\CompanyFinanceDTO;
use App\Interfaces\CompanyFinanceRepositoryInterface;
use App\Models\CompanyFinance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function getAllPaginated(
        ?string $search = null,
        int $rowPerPage = 15
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        int $id
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

    public function update(
        int $id,
        array $data
    ): CompanyFinance {
        $finance = $this->getById($id);
        $dto = CompanyFinanceDTO::fromArrayForUpdate($data, $finance);
        $finance->update($dto->toArray());

        return $finance;
    }

    public function delete(
        int $id
    ): CompanyFinance {
        $finance = $this->getById($id);
        $finance->delete();

        return $finance;
    }

    public function first(): ?CompanyFinance
    {
        return CompanyFinance::first();
    }
}