<?php

namespace App\Enums;

enum PtkpStatus: string
{
    case TK0 = 'TK/0';
    case TK1 = 'TK/1';
    case TK2 = 'TK/2';
    case TK3 = 'TK/3';
    case K0 = 'K/0';
    case K1 = 'K/1';
    case K2 = 'K/2';
    case K3 = 'K/3';

    public function label(): string
    {
        return match ($this) {
            self::TK0 => 'TK/0 - Tidak Kawin, 0 Tanggungan',
            self::TK1 => 'TK/1 - Tidak Kawin, 1 Tanggungan',
            self::TK2 => 'TK/2 - Tidak Kawin, 2 Tanggungan',
            self::TK3 => 'TK/3 - Tidak Kawin, 3 Tanggungan',
            self::K0 => 'K/0 - Kawin, 0 Tanggungan',
            self::K1 => 'K/1 - Kawin, 1 Tanggungan',
            self::K2 => 'K/2 - Kawin, 2 Tanggungan',
            self::K3 => 'K/3 - Kawin, 3 Tanggungan',
        };
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
