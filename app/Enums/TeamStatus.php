<?php

namespace App\Enums;

enum TeamStatus: string
{
    case ACTIVE = 'active';
    case FORMING = 'forming';
    case PLANNING = 'planning';
    case DORMANT = 'dormant';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::FORMING => 'Forming',
            self::PLANNING => 'Planning',
            self::DORMANT => 'Dormant',
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
