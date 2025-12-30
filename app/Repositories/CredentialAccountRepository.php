<?php

namespace App\Repositories;

use App\DTOs\CredentialAccountDto;
use App\Interfaces\CredentialAccountRepositoryInterface;
use App\Models\CredentialAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CredentialAccountRepository implements CredentialAccountRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = CredentialAccount::query()
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
    ): CredentialAccount {
        return CredentialAccount::findOrFail($id);
    }

    public function create(
        array $data
    ): CredentialAccount {
        $accountDto = CredentialAccountDto::fromArray($data);
        $accountArray = $accountDto->toArray();

        return CredentialAccount::create($accountArray);
    }

    public function update(
        string $id,
        array $data
    ): CredentialAccount {
        $account = $this->getById($id);
        $accountDto = CredentialAccountDto::fromArrayForUpdate($data, $account);
        $account->update($accountDto->toArray());

        return $account;
    }

    public function delete(
        string $id
    ): CredentialAccount {
        $account = $this->getById($id);
        $account->delete();

        return $account;
    }
}

