<?php

namespace App\Services;

use App\Models\DocumentNumberSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Atomically get the next sequence number for a (document_type, scope_key, year) bucket.
     * scope_key is always a non-null string (use '' when there is no sub-scope) so the
     * unique index reliably prevents duplicate numbers under concurrent requests.
     */
    public function nextSequence(string $documentType, string $scopeKey, int $year): int
    {
        return DB::transaction(function () use ($documentType, $scopeKey, $year) {
            $sequence = DocumentNumberSequence::where('document_type', $documentType)
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // Handle the race where two requests try to create the first row at once.
                $sequence = DocumentNumberSequence::firstOrCreate(
                    ['document_type' => $documentType, 'scope_key' => $scopeKey, 'year' => $year],
                    ['last_number' => 0]
                );
                $sequence = DocumentNumberSequence::where('id', $sequence->id)->lockForUpdate()->first();
            }

            $sequence->increment('last_number');

            return $sequence->last_number;
        });
    }

    public function romanMonth(int $month): string
    {
        return self::ROMAN_MONTHS[$month] ?? '';
    }

    /**
     * PO/[NomorUrut 3-digit]/[E|I]/OPS/JCD/[BulanRomawi]/[Tahun 4-digit]
     */
    public function generatePoNumber(string $type, Carbon $date): string
    {
        $seq = $this->nextSequence('po', '', (int) $date->year);

        return sprintf('PO/%03d/%s/OPS/JCD/%s/%d', $seq, $type, $this->romanMonth($date->month), $date->year);
    }

    /**
     * INV/JCD-[KodeKlien]/[DD][MM]/[YY].[NomorUrut 3-digit]
     */
    public function generateInvoiceNumber(string $clientCode, Carbon $date): string
    {
        $year2 = $date->format('y');
        $seq = $this->nextSequence('invoice', '', (int) $date->year);

        return sprintf('INV/JCD-%s/%s%s/%s.%03d', strtoupper($clientCode), $date->format('d'), $date->format('m'), $year2, $seq);
    }

    /**
     * RCP/JCD-[KodeKlien]/[DD][MM]/[YY].[NomorUrut 3-digit]
     */
    public function generateReceiptNumber(string $clientCode, Carbon $date): string
    {
        $year2 = $date->format('y');
        $seq = $this->nextSequence('receipt', '', (int) $date->year);

        return sprintf('RCP/JCD-%s/%s%s/%s.%03d', strtoupper($clientCode), $date->format('d'), $date->format('m'), $year2, $seq);
    }

    /**
     * [NomorUrut 3-digit]/[I|E]/[KodeSurat]-JCD/[KodeDivisi]/[BulanRomawi]/[Tahun 4-digit]
     * Sequence is scoped per letter code (kode surat) per year, shared across I/E.
     */
    public function generateLetterNumber(string $letterCode, string $type, string $divisionCode, Carbon $date): array
    {
        $year = (int) $date->year;
        $seq = $this->nextSequence('letter', strtoupper($letterCode), $year);

        $number = sprintf(
            '%03d/%s/%s-JCD/%s/%s/%d',
            $seq,
            $type,
            strtoupper($letterCode),
            strtoupper($divisionCode),
            $this->romanMonth($date->month),
            $year
        );

        return ['number' => $number, 'sequence' => $seq, 'year' => $year];
    }
}
