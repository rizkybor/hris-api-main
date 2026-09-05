<?php

namespace App\Enums;

/**
 * Indonesian withholding income tax (PPh) categories relevant to a B2B
 * software/IT services quotation -- the client withholds this from what
 * they pay the company and remits it to the tax office directly, unlike
 * PPN which the company collects on top of the price. Deliberately scoped
 * to categories that actually apply to jasa teknik/IT services (PPh 23)
 * and foreign counterparties (PPh 26), not e.g. PPh 21 (employee payroll)
 * or PPh 4(2) construction, which don't apply to this kind of invoice.
 */
enum PphType: string
{
    case PPH23_NPWP = 'pph23_npwp';
    case PPH23_NO_NPWP = 'pph23_no_npwp';
    case PPH26 = 'pph26';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PPH23_NPWP => 'PPh 23 - Jasa Teknik/IT (dengan NPWP)',
            self::PPH23_NO_NPWP => 'PPh 23 - Jasa Teknik/IT (tanpa NPWP)',
            self::PPH26 => 'PPh 26 - Wajib Pajak Luar Negeri',
            self::CUSTOM => 'Custom',
        };
    }

    /**
     * Standard statutory rate for this category, or null for Custom where
     * the user supplies their own rate.
     */
    public function defaultRate(): ?float
    {
        return match ($this) {
            self::PPH23_NPWP => 2.0,
            self::PPH23_NO_NPWP => 4.0,
            self::PPH26 => 20.0,
            self::CUSTOM => null,
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'default_rate' => $this->defaultRate(),
        ];
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => $case->toArray(), self::cases());
    }
}
