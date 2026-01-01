<?php

namespace App\Enums;

enum InfrastructureToolStatus: string
{
    case ACTIVE = 'active';
    case ACTIVE_MONITORING_REQUIRED = 'active_monitoring_required';
    case ACTIVE_NON_CRITICAL = 'active_non_critical';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ACTIVE_MONITORING_REQUIRED => 'Active Monitoring Required',
            self::ACTIVE_NON_CRITICAL => 'Active Non Critical',
            self::INACTIVE => 'Inactive',
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

