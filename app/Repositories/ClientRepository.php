<?php

namespace App\Repositories;

use App\DTOs\ClientDto;
use App\Interfaces\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClientRepository implements ClientRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|Collection {
        $query = Client::query()
            ->withAvg('evaluations', 'rating')
            ->withCount('evaluations')
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
    ): Client {
        return Client::withAvg('evaluations', 'rating')->withCount('evaluations')->findOrFail($id);
    }

    public function getStatistic(): array
    {
        // Statistik berdasarkan TYPE
        $byType = Client::query()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        // Statistik berdasarkan FIELD
        $byField = Client::query()
            ->select('field', DB::raw('COUNT(*) as total'))
            ->groupBy('field')
            ->orderByDesc('total')
            ->get();

        // Total client keseluruhan
        $totalClient = Client::count();

        return [
            'by_type' => $byType,
            'by_field' => $byField,
            'summary' => [
                'total_clients' => $totalClient,
            ],
        ];
    }


    public function create(
        array $data
    ): Client {
        $accountDto = ClientDto::fromArray($data);
        $accountArray = $accountDto->toArray();

        return Client::create($accountArray);
    }

    public function update(
        string $id,
        array $data
    ): Client {
        $account = $this->getById($id);
        $accountDto = ClientDto::fromArrayForUpdate($data, $account);
        $account->update($accountDto->toArray());

        return $account;
    }

    public function delete(
        string $id
    ): Client {
        $account = $this->getById($id);
        $account->delete();

        return $account;
    }
}
