<?php

namespace App\Repositories;

use App\DTOs\InfrastructureToolDto;
use App\Interfaces\InfrastructureToolRepositoryInterface;
use App\Models\InfrastructureTool;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        // Query semua Infrastructure
        $query = InfrastructureTool::query();

        if ($search) {
            $query->search($search);
        }

        $items = $query->get();

        // Filter hanya yang aktif
        $activeItems = $items->where('status', 'active');

        // Hitung total bulanan dan tahunan hanya dari aktif
        $totalMonthly = $activeItems->sum('monthly_fee');
        $totalAnnual = $activeItems->sum('annual_fee');

        // Total infrastructure aktif (jumlah record aktif)
        $totalInfraActive = $activeItems->count();

        return [
            'items' => $items, // semua atau bisa diganti $activeItems jika mau
            'summary' => [
                'total_monthly_fee' => $totalMonthly,
                'total_annual_fee' => $totalAnnual,
                'total_infra_active' => $totalInfraActive,
            ],
        ];
    }
    public function getMonthlyStatistic(): array
    {
        $result = InfrastructureTool::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('SUM(monthly_fee) as total_monthly_fee')
            ->selectRaw('SUM(annual_fee) as total_annual_fee')
            ->selectRaw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as total_infra_active')
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderByDesc('month')
            ->get();
        return $result->toArray();
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
