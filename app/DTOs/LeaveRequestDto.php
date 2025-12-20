<?php

namespace App\DTOs;

use App\Models\LeaveRequest;

class LeaveRequestDto
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $employeeId,
        public readonly string $leaveType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?int $totalDays,
        public readonly string $reason,
        public readonly ?string $emergencyContact,
        public readonly string $status,
        public readonly ?string $approvedBy
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'leave_type' => $this->leaveType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_days' => $this->totalDays,
            'reason' => $this->reason,
            'emergency_contact' => $this->emergencyContact,
            'status' => $this->status,
            'approved_by' => $this->approvedBy,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            employeeId: $data['employee_id'],
            leaveType: $data['leave_type'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            totalDays: $data['total_days'] ?? null,
            reason: $data['reason'],
            emergencyContact: $data['emergency_contact'] ?? null,
            status: $data['status'] ?? 'pending',
            approvedBy: $data['approved_by'] ?? null
        );
    }

    public static function fromArrayForUpdate(array $data, LeaveRequest $existingLeaveRequest): self
    {
        return new self(
            id: $existingLeaveRequest->id,
            employeeId: $data['employee_id'] ?? $existingLeaveRequest->employee_id,
            leaveType: $data['leave_type'] ?? $existingLeaveRequest->leave_type->value,
            startDate: $data['start_date'] ?? $existingLeaveRequest->start_date,
            endDate: $data['end_date'] ?? $existingLeaveRequest->end_date,
            totalDays: $data['total_days'] ?? $existingLeaveRequest->total_days,
            reason: $data['reason'] ?? $existingLeaveRequest->reason,
            emergencyContact: $data['emergency_contact'] ?? $existingLeaveRequest->emergency_contact,
            status: $data['status'] ?? $existingLeaveRequest->status,
            approvedBy: $data['approved_by'] ?? $existingLeaveRequest->approved_by
        );
    }
}
