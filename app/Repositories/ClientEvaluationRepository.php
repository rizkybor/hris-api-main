<?php

namespace App\Repositories;

use App\DTOs\ClientEvaluationDto;
use App\Interfaces\ClientEvaluationRepositoryInterface;
use App\Models\ClientEvaluation;
use Illuminate\Database\Eloquent\Collection;

class ClientEvaluationRepository implements ClientEvaluationRepositoryInterface
{
    public function getByClient(string $clientId): Collection
    {
        return ClientEvaluation::with('evaluator')
            ->where('client_id', $clientId)
            ->orderByDesc('evaluated_at')
            ->get();
    }

    public function create(array $data): ClientEvaluation
    {
        $dto = ClientEvaluationDto::fromArray($data);

        return ClientEvaluation::create($dto->toArray())->load('evaluator');
    }

    public function delete(string $id): void
    {
        ClientEvaluation::findOrFail($id)->delete();
    }
}
