<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use Carbon\Carbon;

/**
 * Builds employee codes in the format:
 *   [COUNTRY]JCD[YEAR]-[COMPANY ID][EMPLOYMENT TYPE][5-DIGIT ID]-[CHECK DIGIT]
 *   e.g. IDJCD26-001F00125-3
 *
 * The 5-digit sequence is scoped per (hire year, employment type) so each
 * status keeps its own running series, matching how the numbers read in
 * practice (Full-Time #00125 says nothing about how many Interns exist).
 *
 * Re-issuing: if an Intern later becomes Full-Time, callers should NOT
 * regenerate the code by default -- the employment_type on job_information
 * is updated in place and the original code (and its history) stays intact.
 * A new code is only produced when explicitly requested (see reissue()).
 */
class EmployeeCodeGenerator
{
    private const STATUS_LETTERS = [
        'full_time' => 'F',
        'contract' => 'C',
        'part_time' => 'P',
        'intern' => 'I',
    ];

    /**
     * Numeric stand-ins for the status letter, used only to feed the check
     * digit calculation (a Luhn digit needs a number, not a letter).
     */
    private const STATUS_NUMERIC = [
        'full_time' => 1,
        'contract' => 5,
        'part_time' => 7,
        'intern' => 9,
    ];

    public function generate(string $employmentType, ?string $hireDate = null): string
    {
        $year = $hireDate ? Carbon::parse($hireDate)->format('y') : now()->format('y');
        $statusLetter = self::STATUS_LETTERS[$employmentType] ?? 'F';
        $statusNumeric = self::STATUS_NUMERIC[$employmentType] ?? 1;

        $country = config('employee_code.country_code');
        $groupPrefix = config('employee_code.group_prefix');
        $companyId = config('employee_code.company_id');

        $sequence = str_pad((string) $this->nextSequence($year, $statusLetter), 5, '0', STR_PAD_LEFT);
        $checkDigit = $this->luhnCheckDigit($year.$companyId.$statusNumeric.$sequence);

        return sprintf(
            '%s%s%s-%s%s%s-%s',
            $country,
            $groupPrefix,
            $year,
            $companyId,
            $statusLetter,
            $sequence,
            $checkDigit
        );
    }

    /**
     * Issue a brand new code for an employee whose status has changed
     * (e.g. Intern promoted to Full-Time), when the caller explicitly wants
     * a re-issue instead of keeping the original code.
     */
    public function reissue(string $employmentType, ?string $hireDate = null): string
    {
        return $this->generate($employmentType, $hireDate);
    }

    private function nextSequence(string $year, string $statusLetter): int
    {
        $country = config('employee_code.country_code');
        $groupPrefix = config('employee_code.group_prefix');
        $companyId = config('employee_code.company_id');

        $prefix = "{$country}{$groupPrefix}{$year}-{$companyId}{$statusLetter}";

        $last = EmployeeProfile::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->first();

        if (! $last || ! preg_match('/-\d{3}[A-Z](\d{5})-\d$/', $last->code, $matches)) {
            return 1;
        }

        return ((int) $matches[1]) + 1;
    }

    /**
     * Standard Luhn check digit over the code's numeric payload
     * (year + company id + status number + sequence).
     */
    private function luhnCheckDigit(string $numericPayload): int
    {
        $digits = array_reverse(array_map('intval', str_split($numericPayload)));

        $sum = 0;
        foreach ($digits as $index => $digit) {
            if ($index % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
