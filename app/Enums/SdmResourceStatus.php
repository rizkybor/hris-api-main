<?php

namespace App\Enums;

enum SdmResourceStatus: string
{
    case GREEN = 'green';
    case AMBER = 'amber';
    case RED = 'red';

    public function label(): string
    {
        return match ($this) {
            self::GREEN => 'Green',
            self::AMBER => 'Amber',
            self::RED => 'Red',
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

