<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LeaveRequestStoreRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\LeaveRequestRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LeaveRequestController extends Controller implements HasMiddleware
{
    private LeaveRequestRepositoryInterface $leaveRequestRepository;

    public function __construct(LeaveRequestRepositoryInterface $leaveRequestRepository)
    {
        $this->leaveRequestRepository = $leaveRequestRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['leave-request-list']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['leave-request-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['leave-request-approve']), only: ['approve', 'reject']),
            new Middleware(PermissionMiddleware::using(['leave-request-my-requests']), only: ['getMyLeaveRequests']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $leaveRequests = $this->leaveRequestRepository->getAll(
                $request->search,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Leave Requests Retrieved Successfully', LeaveRequestResource::collection($leaveRequests), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
        ]);

        try {
            $leaveRequests = $this->leaveRequestRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page']
            );

            return ResponseHelper::jsonResponse(true, 'Leave Requests Retrieved Successfully', PaginateResource::make($leaveRequests, LeaveRequestResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getMyLeaveRequests()
    {
        try {
            $leaveRequests = $this->leaveRequestRepository->getMyLeaveRequests();

            return ResponseHelper::jsonResponse(true, 'My Leave Requests Retrieved Successfully', LeaveRequestResource::collection($leaveRequests), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeaveRequestStoreRequest $request)
    {
        $data = $request->validated();

        try {
            $leaveRequest = $this->leaveRequestRepository->store($data);

            return ResponseHelper::jsonResponse(true, 'Leave Request Created Successfully', new LeaveRequestResource($leaveRequest), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Retrieved Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function approve(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->approve($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Approved Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function reject(string $id)
    {
        try {
            $leaveRequest = $this->leaveRequestRepository->reject($id);

            return ResponseHelper::jsonResponse(true, 'Leave Request Rejected Successfully', new LeaveRequestResource($leaveRequest), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Leave Request Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
