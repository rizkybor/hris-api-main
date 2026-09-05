<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ClientController extends Controller implements HasMiddleware
{
    private ClientRepositoryInterface $clientsRepository;

    public function __construct(ClientRepositoryInterface $clientsRepository)
    {
        $this->clientsRepository = $clientsRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['clients-menu|clients-list|clients-create|clients-edit|clients-delete']), only: ['index', 'getAllPaginated', 'show', 'getStatistic']),
            new Middleware(PermissionMiddleware::using(['clients-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['clients-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['clients-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $clients = $this->clientsRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            // Eager load relasi agar taskPivots & attachments muncul
            $clients->load([
                'taskPivots.taskClient',
                'taskPivots.paymentClient',
                'taskPivots.scopeClient',
                'attachments',
                'projects',
            ]);

            return ResponseHelper::jsonResponse(
                true,
                'Client Retrieved Successfully',
                ClientResource::collection($clients),
                200
            );
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
            $clients = $this->clientsRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Client Retrieved Successfully', PaginateResource::make($clients, ClientResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClientStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $clients = $this->clientsRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Client Created Successfully', new ClientResource($clients), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getStatistic()
    {
        try {
            $data = $this->clientsRepository->getStatistic();

            return ResponseHelper::jsonResponse(
                true,
                'Client statistic loaded successfully',
                $data,
                200
            );
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(
                false,
                'Internal Server Error: ' . $e->getMessage(),
                null,
                500
            );
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $client = $this->clientsRepository->getById($id);

            // Eager load relasi
            $client->load([
                'taskPivots.taskClient',
                'taskPivots.paymentClient',
                'taskPivots.scopeClient',
                'attachments',
                'projects',
                'evaluations.evaluator',
            ]);

            return ResponseHelper::jsonResponse(
                true,
                'Client Account Retrieved Successfully',
                new ClientResource($client),
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(ClientUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $clients = $this->clientsRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Client Account Updated Successfully', new ClientResource($clients), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->clientsRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Client Account Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Client Account Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
