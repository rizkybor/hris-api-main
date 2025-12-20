<?php

namespace App\Enums;

enum AccountType: string
{
    case SAVINGS = 'savings';
    case CHECKING = 'checking';
    case CURRENT = 'current';

    public function label(): string
    {
        return match ($this) {
            self::SAVINGS => 'Savings Account',
            self::CHECKING => 'Checking Account',
            self::CURRENT => 'Current Account',
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
