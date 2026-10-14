<?php

namespace App\Enums;

/**
 * Usage status shared by every Operational Cost item (Fixed Cost, SDM
 * Resource, Infrastructure Tool). Only items in use count toward the
 * operational cost totals and the Project Calculator's team cost; items not
 * in use stay listed for reference.
 */
enum OperationalCostStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Digunakan',
            self::INACTIVE => 'Tidak Digunakan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
