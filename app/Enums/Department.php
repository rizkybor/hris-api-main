<?php

namespace App\Enums;

enum Department: string
{
    case DEVELOPMENT = 'development';
    case DESIGN = 'design';
    case MARKETING = 'marketing';
    case SALES = 'sales';
    case SUPPORT = 'support';
    case MANAGEMENT = 'management';

    public function label(): string
    {
        return match ($this) {
            self::DEVELOPMENT => 'Development',
            self::DESIGN => 'Design',
            self::MARKETING => 'Marketing',
            self::SALES => 'Sales',
            self::SUPPORT => 'Support',
            self::MANAGEMENT => 'Management',
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
