<?php

namespace App\Enums;

enum WorkLocation: string
{
    case OFFICE = 'office';
    case REMOTE = 'remote';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::OFFICE => 'Office',
            self::REMOTE => 'Remote',
            self::HYBRID => 'Hybrid',
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
