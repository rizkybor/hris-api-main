<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\ClientTaskList;
use App\Http\Requests\ClientTaskListStoreRequest;
use App\Http\Requests\ClientTaskListUpdateRequest;
use App\Http\Resources\ClientTaskListResource;

use App\Models\ClientTaskPivot;
use App\Http\Resources\ClientTaskPivotResource;

use App\Http\Resources\PaginateResource;
use App\Interfaces\ClientTaskListRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientTaskListController extends Controller implements HasMiddleware
{
    private ClientTaskListRepositoryInterface $clientsTaskListRepository;

    public function __construct(ClientTaskListRepositoryInterface $clientsTaskListRepository)
    {
        $this->clientsTaskListRepository = $clientsTaskListRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-task-list|clients-task-list-create|clients-task-list-edit|clients-task-list-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['clients-task-list-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-task-list-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['clients-task-list-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->clientsTaskListRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Client Task List Retrieved Successfully', ClientTaskListResource::collection($accounts), 200);
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
            $clients = $this->clientsTaskListRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Client Task List Retrieved Successfully', PaginateResource::make($clients, ClientTaskListResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientTaskListStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = ClientTaskList::create([
                'name' => $validated['name'],
            ]);

            $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->task_client_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task List Created and Pivot Updated Successfully',
                [
                    'task'  => new ClientTaskListResource($task),
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
            $clients = $this->clientsTaskListRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Client Account Retrieved Successfully', new ClientTaskListResource($clients), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(ClientTaskListUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->clientsTaskListRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->task_client_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task List Updated Successfully',
                [
                    'task'  => new ClientTaskListResource($task),
                    'pivot' => isset($pivot) ? new ClientTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task List or Pivot Not Found', null, 404);
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
            $this->clientsTaskListRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Account Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
