<?php

namespace App\Enums;

enum LeaveType: string
{
    case ANNUAL_LEAVE = 'annual_leave';
    case SICK_LEAVE = 'sick_leave';
    case PERSONAL_LEAVE = 'personal_leave';
    case EMERGENCY_LEAVE = 'emergency_leave';
    case MATERNITY_LEAVE = 'maternity_leave';
    case PATERNITY_LEAVE = 'paternity_leave';
    case COMPASSIONATE_LEAVE = 'compassionate_leave';

    public function label(): string
    {
        return match ($this) {
            self::ANNUAL_LEAVE => 'Annual Leave',
            self::SICK_LEAVE => 'Sick Leave',
            self::PERSONAL_LEAVE => 'Personal Leave',
            self::EMERGENCY_LEAVE => 'Emergency Leave',
            self::MATERNITY_LEAVE => 'Maternity Leave',
            self::PATERNITY_LEAVE => 'Paternity Leave',
            self::COMPASSIONATE_LEAVE => 'Compassionate Leave',
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
