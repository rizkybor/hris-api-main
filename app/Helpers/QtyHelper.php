<?php

namespace App\Helpers;

/**
 * Item quantities are free text on the forms ("5 unit", "1 paket", "3",
 * "Lumpsum"). PDFs print them as two columns -- number | unit -- so the
 * figures and units each line up down the table.
 */
class QtyHelper
{
    /**
     * @return array{0: ?string, 1: string} [number, unit]; number is null
     *                                      when the text doesn't start with one.
     */
    public static function split(mixed $qty): array
    {
        $text = trim((string) $qty);

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(.*)$/u', $text, $m)) {
            return [$m[1], trim($m[2])];
        }

        return [null, $text];
    }
}
