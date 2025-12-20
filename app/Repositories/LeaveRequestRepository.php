<?php

namespace App\Repositories;

use App\DTOs\LeaveRequestDto;
use App\Interfaces\LeaveRequestRepositoryInterface;
use App\Models\LeaveRequest;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(
        private EmailService $emailService
    ) {}

    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ) {
        $query = LeaveRequest::with(['employee.user', 'approver.user'])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            })
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ) {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ) {
        return LeaveRequest::with(['employee.user', 'approver.user'])
            ->findOrFail($id);
    }

    public function getMyLeaveRequests()
    {
        return LeaveRequest::with(['employee.user', 'approver.user'])
            ->where('employee_id', Auth::user()->employeeProfile->id)
            ->whereDate('created_at', '>=', now()->subDays(6)->startOfDay())
            ->whereDate('created_at', '<=', now()->endOfDay())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $data['total_days'] = $startDate->diffInDays($endDate) + 1;

            $leaveRequestDto = LeaveRequestDto::fromArray($data);
            $leaveRequest = LeaveRequest::create($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestCreatedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }

    public function approve(string $id)
    {
        return DB::transaction(function () use ($id) {
            $leaveRequest = $this->getById($id);

            $data = [
                'status' => 'approved',
                'approved_by' => Auth::user()->employeeProfile->id,
            ];

            $leaveRequestDto = LeaveRequestDto::fromArrayForUpdate($data, $leaveRequest);
            $leaveRequest->update($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestApprovedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }

    public function reject(string $id)
    {
        return DB::transaction(function () use ($id) {
            $leaveRequest = $this->getById($id);

            $data = [
                'status' => 'rejected',
                'approved_by' => Auth::user()->employeeProfile->id,
            ];

            $leaveRequestDto = LeaveRequestDto::fromArrayForUpdate($data, $leaveRequest);
            $leaveRequest->update($leaveRequestDto->toArray());

            DB::afterCommit(function () use ($leaveRequest) {
                $this->emailService->sendLeaveRequestRejectedNotification($leaveRequest);
            });

            return $leaveRequest;
        });
    }
}
