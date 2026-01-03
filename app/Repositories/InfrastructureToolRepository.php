<?php

namespace App\Repositories;

use App\DTOs\InfrastructureToolDTO;
use App\Interfaces\InfrastructureToolRepositoryInterface;
use App\Models\InfrastructureTool;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InfrastructureToolRepository implements InfrastructureToolRepositoryInterface
{
    public function getAll(
        ?string $search = null,
        ?int $limit = null,
        bool $execute = true
    ): Builder|Collection {
        $query = InfrastructureTool::query()
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
    ): InfrastructureTool {
        return InfrastructureTool::findOrFail($id);
    }

    public function getStatistic(?string $search = null): array
    {
        $query = InfrastructureTool::query();

        if ($search) {
            $query->search($search);
        }

        $items = $query->get();

        $totalMonthly = $items->sum('monthly_fee');
        $totalAnnual = $items->sum('annual_fee');

        return [
            'items' => $items,
            'summary' => [
                'total_monthly_fee' => $totalMonthly,
                'total_annual_fee' => $totalAnnual,
            ]
        ];
    }


    public function create(
        array $data
    ): InfrastructureTool {
        $dto = InfrastructureToolDTO::fromArray($data);
        $array = $dto->toArray();

        return InfrastructureTool::create($array);
    }

    public function update(
        string $id,
        array $data
    ): InfrastructureTool {
        $tool = $this->getById($id);
        $dto = InfrastructureToolDTO::fromArrayForUpdate($data, $tool);
        $tool->update($dto->toArray());

        return $tool;
    }

    public function delete(
        string $id
    ): InfrastructureTool {
        $tool = $this->getById($id);
        $tool->delete();

        return $tool;
    }

    public function sum(string $column): float
    {
        return InfrastructureTool::sum($column);
    }
}
