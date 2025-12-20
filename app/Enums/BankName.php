<?php

namespace App\Enums;

enum BankName: string
{
    case BCA = 'bca';
    case MANDIRI = 'mandiri';
    case BNI = 'bni';
    case BRI = 'bri';
    case CIMB_NIAGA = 'cimb_niaga';
    case DANAMON = 'danamon';
    case PERMATA = 'permata';
    case MAYBANK_INDONESIA = 'maybank_indonesia';
    case OCBC_NISP = 'ocbc_nisp';
    case PANIN_BANK = 'panin_bank';

    public function label(): string
    {
        return match ($this) {
            self::BCA => 'Bank Central Asia (BCA)',
            self::MANDIRI => 'Bank Mandiri',
            self::BNI => 'Bank Negara Indonesia (BNI)',
            self::BRI => 'Bank Rakyat Indonesia (BRI)',
            self::CIMB_NIAGA => 'CIMB Niaga',
            self::DANAMON => 'Bank Danamon',
            self::PERMATA => 'Bank Permata',
            self::MAYBANK_INDONESIA => 'Maybank Indonesia',
            self::OCBC_NISP => 'OCBC NISP',
            self::PANIN_BANK => 'Panin Bank',
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
