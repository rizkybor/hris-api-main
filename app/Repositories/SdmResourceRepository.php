<?php

namespace App\Repositories;

use App\DTOs\SdmResourceDto;
use App\Interfaces\SdmResourceRepositoryInterface;
use App\Models\SdmResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SdmResourceRepository implements SdmResourceRepositoryInterface
{
    public function getAll(
        ?string $search = null,
        ?int $limit = null,
        bool $execute = true
    ): Builder|Collection {
        $query = SdmResource::query()
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
        string $id
    ): SdmResource {
        return SdmResource::findOrFail($id);
    }

    public function getStatistic(?string $search = null): array
    {
        $query = SdmResource::query();

        if ($search) {
            $query->search($search);
        }

        $items = $query->get();

        $totalBudget = $items->sum('budget');
        $totalActual = $items->sum('actual');
        $variance = $totalBudget - $totalActual;

        // Hitung jumlah berdasarkan rag_status
        $totalStatusGreen = $items->where('rag_status', 'green')->count();
        $totalStatusAmber = $items->where('rag_status', 'amber')->count();
        $totalStatusRed = $items->where('rag_status', 'red')->count();

        return [
            'items' => $items,
            'summary' => [
                'total_budget' => $totalBudget,
                'total_actual' => $totalActual,
                'variance' => $variance,
                'total_status_green' => $totalStatusGreen,
                'total_status_amber' => $totalStatusAmber,
                'total_status_red' => $totalStatusRed,
            ]
        ];
    }

    public function getMonthlyStatistic(): array
    {
        $result = SdmResource::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('SUM(budget) as total_budget')
            ->selectRaw('SUM(actual) as total_actual')
            ->selectRaw('SUM(budget - actual) as variance')
            ->selectRaw('COUNT(*) as total_items')
            ->groupBy(groups: DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderByDesc('month')
            ->get();

        return $result->toArray();
    }

    public function create(
        array $data
    ): SdmResource {
        $dto = SdmResourceDTO::fromArray($data);
        $array = $dto->toArray();

        return SdmResource::create($array);
    }

    public function update(
        string $id,
        array $data
    ): SdmResource {
        $resource = $this->getById($id);
        $dto = SdmResourceDTO::fromArrayForUpdate($data, $resource);
        $resource->update($dto->toArray());

        return $resource;
    }

    public function delete(
        string $id
    ): SdmResource {
        $resource = $this->getById($id);
        $resource->delete();

        return $resource;
    }

    public function sum(string $column): float
    {
        return SdmResource::sum($column);
    }
}
