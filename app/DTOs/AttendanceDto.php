<?php

namespace App\DTOs;

use App\Models\Attendance;

class AttendanceDto
{
    public function __construct(
        public readonly int $employee_id,
        public readonly string $date,
        public readonly ?string $check_in,
        public readonly ?float $check_in_lat,
        public readonly ?float $check_in_long,
        public readonly ?string $check_out,
        public readonly ?float $check_out_lat,
        public readonly ?float $check_out_long,
        public readonly string $status,
        public readonly ?string $notes = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'date' => $this->date,
            'check_in' => $this->check_in,
            'check_in_lat' => $this->check_in_lat,
            'check_in_long' => $this->check_in_long,
            'check_out' => $this->check_out,
            'check_out_lat' => $this->check_out_lat,
            'check_out_long' => $this->check_out_long,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            employee_id: $data['employee_id'],
            date: $data['date'],
            check_in: $data['check_in'] ?? null,
            check_in_lat: isset($data['check_in_lat']) ? (float) $data['check_in_lat'] : null,
            check_in_long: isset($data['check_in_long']) ? (float) $data['check_in_long'] : null,
            check_out: $data['check_out'] ?? null,
            check_out_lat: isset($data['check_out_lat']) ? (float) $data['check_out_lat'] : null,
            check_out_long: isset($data['check_out_long']) ? (float) $data['check_out_long'] : null,
            status: $data['status'],
            notes: $data['notes'] ?? null,
        );
    }

    public static function fromArrayForUpdate(array $data, Attendance $existingAttendance): self
    {
        return new self(
            employee_id: $data['employee_id'] ?? $existingAttendance->employee_id,
            date: $data['date'] ?? ($existingAttendance->date ? $existingAttendance->date : null),
            check_in: $data['check_in'] ?? ($existingAttendance->check_in ? $existingAttendance->check_in : null),
            check_in_lat: isset($data['check_in_lat']) ? (float) $data['check_in_lat'] : $existingAttendance->check_in_lat,
            check_in_long: isset($data['check_in_long']) ? (float) $data['check_in_long'] : $existingAttendance->check_in_long,
            check_out: $data['check_out'] ?? ($existingAttendance->check_out ? $existingAttendance->check_out : null),
            check_out_lat: isset($data['check_out_lat']) ? (float) $data['check_out_lat'] : $existingAttendance->check_out_lat,
            check_out_long: isset($data['check_out_long']) ? (float) $data['check_out_long'] : $existingAttendance->check_out_long,
            status: $data['status'] ?? $existingAttendance->status,
            notes: $data['notes'] ?? $existingAttendance->notes,
        );
    }
}
