<?php

namespace App\Helpers;

class TerbilangHelper
{
    private static array $units = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
        'Sepuluh', 'Sebelas',
    ];

    public static function convert(int $number): string
    {
        if ($number < 0) {
            return 'Minus '.self::convert(abs($number));
        }

        if ($number < 12) {
            return self::$units[$number];
        }

        if ($number < 20) {
            return self::convert($number - 10).' Belas';
        }

        if ($number < 100) {
            return trim(self::convert(intdiv($number, 10)).' Puluh '.self::convert($number % 10));
        }

        if ($number < 200) {
            return trim('Seratus '.self::convert($number - 100));
        }

        if ($number < 1000) {
            return trim(self::convert(intdiv($number, 100)).' Ratus '.self::convert($number % 100));
        }

        if ($number < 2000) {
            return trim('Seribu '.self::convert($number - 1000));
        }

        if ($number < 1000000) {
            return trim(self::convert(intdiv($number, 1000)).' Ribu '.self::convert($number % 1000));
        }

        if ($number < 1000000000) {
            return trim(self::convert(intdiv($number, 1000000)).' Juta '.self::convert($number % 1000000));
        }

        if ($number < 1000000000000) {
            return trim(self::convert(intdiv($number, 1000000000)).' Miliar '.self::convert($number % 1000000000));
        }

        return trim(self::convert(intdiv($number, 1000000000000)).' Triliun '.self::convert($number % 1000000000000));
    }

    public static function toRupiah(float $amount): string
    {
        $words = self::convert((int) round($amount));

        return preg_replace('/\s+/', ' ', $words).' Rupiah';
    }
}
