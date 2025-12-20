<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case ABSENT = 'absent';
    case HALF_DAY = 'half_day';
    case SICK_LEAVE = 'sick_leave';
    case ANNUAL_LEAVE = 'annual_leave';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::LATE => 'Late',
            self::ABSENT => 'Absent',
            self::HALF_DAY => 'Half Day',
            self::SICK_LEAVE => 'Sick Leave',
            self::ANNUAL_LEAVE => 'Annual Leave',
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}
