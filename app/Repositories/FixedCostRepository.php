<?php

namespace App\Repositories;

use App\DTOs\FixedCostDto;
use App\Interfaces\FixedCostRepositoryInterface;
use App\Models\FixedCost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FixedCostRepository implements FixedCostRepositoryInterface
{
    public function getAll(
        ?string $search = null,
        ?int $limit = null,
        bool $execute = true
    ): Builder|Collection {
        $query = FixedCost::query()
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
    ): FixedCost {
        return FixedCost::findOrFail($id);
    }

    public function getStatistic(?string $search = "GitHub"): array
    {
        $query = FixedCost::query();

        // Filter berdasarkan search jika ada
        if ($search) {
            $query->where('financial_items', 'like', "%{$search}%");
        }

        $items = $query->get();

        $totalBudget = $items->sum('budget');
        $totalActual = $items->sum('actual');
        $variance = $totalBudget - $totalActual;
        $totalItems = $items->count(); // total data

        return [
            'items' => $items,
            'summary' => [
                'total_budget' => $totalBudget,
                'total_actual' => $totalActual,
                'variance' => $variance,
                'total_items' => $totalItems,
            ]
        ];
    }

    public function getMonthlyStatistic(): array
    {
        // Menggunakan FixedCost::query() untuk query Eloquent
        $result = FixedCost::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('SUM(budget) as total_budget')
            ->selectRaw('SUM(actual) as total_actual')
            ->selectRaw('SUM(budget - actual) as variance')
            ->selectRaw('COUNT(*) as total_items')
            ->groupBy(groups: DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderByDesc('month')
            ->get();

        return $result->toArray(); // Mengembalikan hasil sebagai array
    }



    public function create(
        array $data
    ): FixedCost {
        $dto = FixedCostDTO::fromArray($data);
        $array = $dto->toArray();

        return FixedCost::create($array);
    }

    public function update(
        string $id,
        array $data
    ): FixedCost {
        $cost = $this->getById($id);
        $dto = FixedCostDTO::fromArrayForUpdate($data, $cost);
        $cost->update($dto->toArray());

        return $cost;
    }

    public function delete(
        string $id
    ): FixedCost {
        $cost = $this->getById($id);
        $cost->delete();

        return $cost;
    }

    public function sum(string $column): float
    {
        return FixedCost::sum($column);
    }
}
