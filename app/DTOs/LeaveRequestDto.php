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
        public readonly ?float $totalDays,
        public readonly bool $isHalfDay,
        public readonly string $reason,
        public readonly ?string $emergencyContact,
        public readonly ?string $attachmentOriginalName,
        public readonly ?string $attachmentPath,
        public readonly ?string $attachmentMimeType,
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
            'is_half_day' => $this->isHalfDay,
            'reason' => $this->reason,
            'emergency_contact' => $this->emergencyContact,
            'attachment_original_name' => $this->attachmentOriginalName,
            'attachment_path' => $this->attachmentPath,
            'attachment_mime_type' => $this->attachmentMimeType,
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
            totalDays: isset($data['total_days']) ? (float) $data['total_days'] : null,
            isHalfDay: (bool) ($data['is_half_day'] ?? false),
            reason: $data['reason'],
            emergencyContact: $data['emergency_contact'] ?? null,
            attachmentOriginalName: $data['attachment_original_name'] ?? null,
            attachmentPath: $data['attachment_path'] ?? null,
            attachmentMimeType: $data['attachment_mime_type'] ?? null,
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
            totalDays: isset($data['total_days']) ? (float) $data['total_days'] : $existingLeaveRequest->total_days,
            isHalfDay: (bool) ($data['is_half_day'] ?? $existingLeaveRequest->is_half_day),
            reason: $data['reason'] ?? $existingLeaveRequest->reason,
            emergencyContact: $data['emergency_contact'] ?? $existingLeaveRequest->emergency_contact,
            attachmentOriginalName: $data['attachment_original_name'] ?? $existingLeaveRequest->attachment_original_name,
            attachmentPath: $data['attachment_path'] ?? $existingLeaveRequest->attachment_path,
            attachmentMimeType: $data['attachment_mime_type'] ?? $existingLeaveRequest->attachment_mime_type,
            status: $data['status'] ?? $existingLeaveRequest->status,
            approvedBy: $data['approved_by'] ?? $existingLeaveRequest->approved_by
        );
    }
}
