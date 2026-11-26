<?php

namespace App\Services;

use App\Models\CertificateSetting;
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

    /**
     * Read-only look at what the next sequence number would be, without
     * consuming it. Used for live "here's what your number will look like"
     * previews so the real counter isn't burned by a form the user never
     * submits.
     */
    public function peekNextSequence(string $documentType, string $scopeKey, int $year): int
    {
        $sequence = DocumentNumberSequence::where('document_type', $documentType)
            ->where('scope_key', $scopeKey)
            ->where('year', $year)
            ->first();

        return ($sequence->last_number ?? 0) + 1;
    }

    /** Company time zone: numbering follows the date in Indonesia (WIB). */
    public const TIMEZONE = 'Asia/Jakarta';

    /** Today in WIB (the app runs in UTC; 1 Jan 00:00-07:00 WIB is still 31 Dec UTC). */
    public function today(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * The date as seen in WIB, so the year (and with it the sequence, which
     * restarts at 001 every year) never comes from a UTC clock. A date-only
     * value (midnight UTC) stays on the same day.
     */
    public function local(Carbon $date): Carbon
    {
        return $date->copy()->setTimezone(self::TIMEZONE);
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
        $date = $this->local($date);
        $seq = $this->nextSequence('po', '', (int) $date->year);

        return sprintf('PO/%03d/%s/OPS/JCD/%s/%d', $seq, $type, $this->romanMonth($date->month), $date->year);
    }

    /**
     * INV/JCD-[KodeKlien]/[DD][MM]/[YY].[NomorUrut 3-digit]
     */
    public function generateInvoiceNumber(string $clientCode, Carbon $date): string
    {
        $date = $this->local($date);
        $year2 = $date->format('y');
        $seq = $this->nextSequence('invoice', '', (int) $date->year);

        return sprintf('INV/JCD-%s/%s%s/%s.%03d', strtoupper($clientCode), $date->format('d'), $date->format('m'), $year2, $seq);
    }

    /**
     * RCP/JCD-[KodeKlien]/[DD][MM]/[YY].[NomorUrut 3-digit]
     */
    public function generateReceiptNumber(string $clientCode, Carbon $date): string
    {
        $date = $this->local($date);
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
        $date = $this->local($date);
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

    /**
     * ND/[NomorUrut 3-digit]/JCD/[BulanRomawi]/[Tahun 4-digit]
     */
    public function generateNotaDinasNumber(Carbon $date): array
    {
        $date = $this->local($date);
        $year = (int) $date->year;
        $seq = $this->nextSequence('nota_dinas', '', $year);

        return [
            'number' => sprintf('ND/%03d/JCD/%s/%d', $seq, $this->romanMonth($date->month), $year),
            'sequence' => $seq,
            'year' => $year,
        ];
    }

    /**
     * MN/[NomorUrut 3-digit]/JCD/[BulanRomawi]/[Tahun 4-digit]
     */
    public function generateMeetingNoteNumber(Carbon $date): array
    {
        $date = $this->local($date);
        $year = (int) $date->year;
        $seq = $this->nextSequence('meeting_note', '', $year);

        return [
            'number' => sprintf('MN/%03d/JCD/%s/%d', $seq, $this->romanMonth($date->month), $year),
            'sequence' => $seq,
            'year' => $year,
        ];
    }

    /**
     * Certificate numbering: sequence resets to 0001 whenever company,
     * category, program, year, or month differ, and increments whenever
     * they're all the same -- so the scope bucket folds company+category+
     * program+month together (year is already its own axis on the
     * sequences table).
     */
    private function certificateScopeKey(string $companyCode, string $categoryCode, string $programCode, string $monthRoman): string
    {
        return strtoupper("{$companyCode}|{$categoryCode}|{$programCode}|{$monthRoman}");
    }

    public function generateCertificateNumber(
        string $companyCode,
        string $categoryCode,
        string $programCode,
        Carbon $date,
        ?string $format = null
    ): array {
        $date = $this->local($date);
        $year = (int) $date->year;
        $monthRoman = $this->romanMonth($date->month);
        $scopeKey = $this->certificateScopeKey($companyCode, $categoryCode, $programCode, $monthRoman);
        $seq = $this->nextSequence('certificate', $scopeKey, $year);

        return [
            'number' => $this->formatCertificateNumber($format, $companyCode, $categoryCode, $programCode, $year, $monthRoman, $seq),
            'sequence' => $seq,
            'year' => $year,
            'month_roman' => $monthRoman,
        ];
    }

    public function peekCertificateNumber(
        string $companyCode,
        string $categoryCode,
        string $programCode,
        Carbon $date,
        ?string $format = null
    ): array {
        $date = $this->local($date);
        $year = (int) $date->year;
        $monthRoman = $this->romanMonth($date->month);
        $scopeKey = $this->certificateScopeKey($companyCode, $categoryCode, $programCode, $monthRoman);
        $seq = $this->peekNextSequence('certificate', $scopeKey, $year);

        return [
            'number' => $this->formatCertificateNumber($format, $companyCode, $categoryCode, $programCode, $year, $monthRoman, $seq),
            'sequence' => $seq,
            'year' => $year,
            'month_roman' => $monthRoman,
        ];
    }

    public function formatCertificateNumber(
        ?string $format,
        string $companyCode,
        string $categoryCode,
        string $programCode,
        int $year,
        string $monthRoman,
        int $sequence
    ): string {
        $format = $format ?: CertificateSetting::DEFAULT_FORMAT;

        return strtr($format, [
            '{company}' => strtoupper($companyCode),
            '{category}' => strtoupper($categoryCode),
            '{program}' => strtoupper($programCode),
            '{year}' => (string) $year,
            '{month_roman}' => $monthRoman,
            '{sequence}' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
        ]);
    }
}
