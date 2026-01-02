<?php

namespace App\Repositories;

use App\DTOs\VendorsDto;
use App\Interfaces\VendorsRepositoryInterface;
use App\Models\Vendors;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VendorsRepository implements VendorsRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Vendors::query()
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
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
        ?string $search,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Vendors {
        return Vendors::findOrFail($id);
    }

    public function create(
        array $data
    ): Vendors {
        $accountDto = VendorsDto::fromArray($data);
        $accountArray = $accountDto->toArray();

        return Vendors::create($accountArray);
    }

    public function update(
        string $id,
        array $data
    ): Vendors {
        $account = $this->getById($id);
        $accountDto = VendorsDto::fromArrayForUpdate($data, $account);
        $account->update($accountDto->toArray());

        return $account;
    }

    public function delete(
        string $id
    ): Vendors {
        $account = $this->getById($id);
        $account->delete();

        return $account;
    }
}
