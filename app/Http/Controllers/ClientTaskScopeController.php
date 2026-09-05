<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\ClientTaskScope;
use App\Http\Requests\ClientTaskScopeStoreRequest;
use App\Http\Requests\ClientTaskScopeUpdateRequest;
use App\Http\Resources\ClientTaskScopeResource;
use App\Interfaces\ClientTaskScopeRepositoryInterface;

use App\Models\ClientTaskPivot;
use App\Http\Resources\ClientTaskPivotResource;

use App\Http\Resources\PaginateResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientTaskScopeController extends Controller implements HasMiddleware
{
    private ClientTaskScopeRepositoryInterface $clientsTaskScopeRepository;

    public function __construct(ClientTaskScopeRepositoryInterface $endorsTaskScopeRepository)
    {
        $this->clientsTaskScopeRepository = $endorsTaskScopeRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-task-scope-list|clients-task-scope-create|clients-task-scope-edit|clients-task-scope-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['clients-task-scope-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-task-scope-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['clients-task-scope-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->clientsTaskScopeRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Client Task Scope Retrieved Successfully', ClientTaskScopeResource::collection($accounts), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $clients = $this->clientsTaskScopeRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Client Task Scope Retrieved Successfully', PaginateResource::make($clients, ClientTaskScopeResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientTaskScopeStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = ClientTaskScope::create([
                'name' => $validated['name'],
            ]);

            $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->scope_client_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task Scope Created and Pivot Updated Successfully',
                [
                    'task'  => new ClientTaskScopeResource($task),
                    'pivot' => new ClientTaskPivotResource($pivot),
                ],
                201
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $clients = $this->clientsTaskScopeRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Client Task Scope Retrieved Successfully', new ClientTaskScopeResource($clients), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Task Scope Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClientTaskScopeUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->clientsTaskScopeRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->scope_client_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task Scope Updated Successfully',
                [
                    'task'  => new ClientTaskScopeResource($task),
                    'pivot' => isset($pivot) ? new ClientTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Scope or Pivot Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->clientsTaskScopeRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Task Scope Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Task Scope Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
