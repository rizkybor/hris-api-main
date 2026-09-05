<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;

use App\Models\ClientTaskPayment;
use App\Http\Requests\ClientTaskPaymentStoreRequest;
use App\Http\Requests\ClientTaskPaymentUpdateRequest;
use App\Http\Resources\ClientTaskPaymentResource;

use App\Models\ClientTaskPivot;
use App\Http\Resources\ClientTaskPivotResource;

use App\Http\Resources\PaginateResource;
use App\Interfaces\ClientTaskPaymentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientTaskPaymentController extends Controller implements HasMiddleware
{
    private ClientTaskPaymentRepositoryInterface $clientsTaskPaymentRepository;

    public function __construct(ClientTaskPaymentRepositoryInterface $clientsTaskPaymentRepository)
    {
        $this->clientsTaskPaymentRepository = $clientsTaskPaymentRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-task-payment-list|clients-task-payment-create|clients-task-payment-edit|clients-task-payment-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['clients-task-payment-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-task-payment-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['clients-task-payment-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $accounts = $this->clientsTaskPaymentRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Client Task Payment Retrieved Successfully', ClientTaskPaymentResource::collection($accounts), 200);
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
            $clients = $this->clientsTaskPaymentRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Client Task Payment Retrieved Successfully', PaginateResource::make($clients, ClientTaskPaymentResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientTaskPaymentStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $task = ClientTaskPayment::create([
                'document_name' => $validated['document_name'],
                'document_path' => $validated['document_path'] ?? null,
                'amount'        => $validated['amount'] ?? null,
                'payment_date'  => $validated['payment_date'] ?? null,
            ]);

            $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
            $pivot->payment_client_id = $task->id;
            $pivot->save();

            return ResponseHelper::jsonResponse(
                true,
                'Task Payment Created and Pivot Updated Successfully',
                [
                    'task'  => new ClientTaskPaymentResource($task),
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
            $clients = $this->clientsTaskPaymentRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Client Task Payment Retrieved Successfully', new ClientTaskPaymentResource($clients), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Task Payment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(ClientTaskPaymentUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $task = $this->clientsTaskPaymentRepository->update($id, $validated);

            if (!empty($validated['pivot_id'])) {
                $pivot = ClientTaskPivot::findOrFail($validated['pivot_id']);
                $pivot->payment_client_id = $task->id;
                $pivot->save();
            }

            return ResponseHelper::jsonResponse(
                true,
                'Task Payment Updated Successfully',
                [
                    'task'  => new ClientTaskPaymentResource($task),
                    'pivot' => isset($pivot) ? new ClientTaskPivotResource($pivot) : null,
                ],
                200
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Task Payment or Pivot Not Found', null, 404);
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
            $this->clientsTaskPaymentRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Task Payment Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Task Payment Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
