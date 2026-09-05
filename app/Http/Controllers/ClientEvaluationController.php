<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ClientEvaluationStoreRequest;
use App\Http\Resources\ClientEvaluationResource;
use App\Interfaces\ClientEvaluationRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientEvaluationController extends Controller implements HasMiddleware
{
    public function __construct(private ClientEvaluationRepositoryInterface $clientEvaluationRepository) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-evaluation-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['clients-evaluation-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-evaluation-delete']), only: ['destroy']),
        ];
    }

    /**
     * List evaluations for one client.
     */
    public function index(string $clientId)
    {
        try {
            $evaluations = $this->clientEvaluationRepository->getByClient($clientId);

            return ResponseHelper::jsonResponse(true, 'Client Evaluations Retrieved Successfully', ClientEvaluationResource::collection($evaluations), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(ClientEvaluationStoreRequest $request)
    {
        $data = $request->validated();
        $data['evaluated_by'] = Auth::id();

        try {
            $evaluation = $this->clientEvaluationRepository->create($data);

            return ResponseHelper::jsonResponse(true, 'Client Evaluation Created Successfully', new ClientEvaluationResource($evaluation), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $this->clientEvaluationRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Evaluation Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Evaluation Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
