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
        public readonly ?string $check_in_photo,
        public readonly ?string $check_out,
        public readonly ?float $check_out_lat,
        public readonly ?float $check_out_long,
        public readonly string $status,
        public readonly ?int $late_minutes = null,
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
            'check_in_photo' => $this->check_in_photo,
            'check_out' => $this->check_out,
            'check_out_lat' => $this->check_out_lat,
            'check_out_long' => $this->check_out_long,
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
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
            check_in_photo: $data['check_in_photo'] ?? null,
            check_out: $data['check_out'] ?? null,
            check_out_lat: isset($data['check_out_lat']) ? (float) $data['check_out_lat'] : null,
            check_out_long: isset($data['check_out_long']) ? (float) $data['check_out_long'] : null,
            status: $data['status'],
            late_minutes: isset($data['late_minutes']) ? (int) $data['late_minutes'] : null,
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
            check_in_photo: $data['check_in_photo'] ?? $existingAttendance->check_in_photo,
            check_out: $data['check_out'] ?? ($existingAttendance->check_out ? $existingAttendance->check_out : null),
            check_out_lat: isset($data['check_out_lat']) ? (float) $data['check_out_lat'] : $existingAttendance->check_out_lat,
            check_out_long: isset($data['check_out_long']) ? (float) $data['check_out_long'] : $existingAttendance->check_out_long,
            status: $data['status'] ?? $existingAttendance->status,
            late_minutes: array_key_exists('late_minutes', $data) ? (is_null($data['late_minutes']) ? null : (int) $data['late_minutes']) : $existingAttendance->late_minutes,
            notes: $data['notes'] ?? $existingAttendance->notes,
        );
    }
}
