<?php

namespace App\Enums;

enum JobStatus: string
{
    case ACTIVE = 'active';
    case ON_LEAVE = 'on_leave';
    case RESIGNED = 'resigned';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ON_LEAVE => 'On Leave',
            self::RESIGNED => 'Resigned',
            self::TERMINATED => 'Terminated',
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
