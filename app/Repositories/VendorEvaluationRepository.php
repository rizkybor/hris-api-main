<?php

namespace App\Repositories;

use App\DTOs\VendorEvaluationDto;
use App\Interfaces\VendorEvaluationRepositoryInterface;
use App\Models\VendorEvaluation;
use Illuminate\Database\Eloquent\Collection;

class VendorEvaluationRepository implements VendorEvaluationRepositoryInterface
{
    public function getByVendor(string $vendorId): Collection
    {
        return VendorEvaluation::with('evaluator')
            ->where('vendor_id', $vendorId)
            ->orderByDesc('evaluated_at')
            ->get();
    }

    public function create(array $data): VendorEvaluation
    {
        $dto = VendorEvaluationDto::fromArray($data);

        return VendorEvaluation::create($dto->toArray())->load('evaluator');
    }

    public function delete(string $id): void
    {
        VendorEvaluation::findOrFail($id)->delete();
    }
}
