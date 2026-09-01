<?php

namespace App\Services;

/**
 * Computes BPJS contributions and PPh21 withholding for a monthly payroll run.
 *
 * PPh21 uses the classic annualized progressive method (gross monthly income
 * x 12, minus biaya jabatan and BPJS JHT/JP, minus annual PTKP, taxed through
 * Pasal 17 UU HPP brackets, divided back by 12) rather than DJP's TER monthly
 * withholding lookup tables mandated since 2024. Annual totals reconcile
 * between the two methods, but month-to-month withholding amounts can differ
 * slightly from the official TER table. Treat this as a good-faith estimate,
 * not a compliance-grade calculation — verify with a tax consultant or
 * payroll/tax software before relying on it for real filings.
 */
class PayrollCalculationService
{
    public function calculate(float $grossMonthlySalary, string $ptkpStatus): array
    {
        $bpjs = $this->calculateBpjs($grossMonthlySalary);
        $pph21 = $this->calculatePph21Monthly($grossMonthlySalary, $bpjs['jht_employee'] + $bpjs['jp_employee'], $ptkpStatus);

        $totalEmployeeDeduction = $bpjs['kesehatan_employee'] + $bpjs['jht_employee'] + $bpjs['jp_employee'] + $pph21;

        return [
            'bpjs_kesehatan_employee' => round($bpjs['kesehatan_employee'], 2),
            'bpjs_jht_employee' => round($bpjs['jht_employee'], 2),
            'bpjs_jp_employee' => round($bpjs['jp_employee'], 2),
            'bpjs_kesehatan_company' => round($bpjs['kesehatan_company'], 2),
            'bpjs_jht_company' => round($bpjs['jht_company'], 2),
            'bpjs_jp_company' => round($bpjs['jp_company'], 2),
            'bpjs_jkk_company' => round($bpjs['jkk_company'], 2),
            'bpjs_jkm_company' => round($bpjs['jkm_company'], 2),
            'pph21' => round($pph21, 2),
            'total_employee_deduction' => round($totalEmployeeDeduction, 2),
            'take_home_pay' => round($grossMonthlySalary - $totalEmployeeDeduction, 2),
        ];
    }

    public function calculateBpjs(float $grossMonthlySalary): array
    {
        $cfg = config('payroll.bpjs');

        $kesehatanBase = min($grossMonthlySalary, $cfg['kesehatan']['salary_cap']);
        $jpBase = min($grossMonthlySalary, $cfg['jp']['salary_cap']);

        return [
            'kesehatan_employee' => $kesehatanBase * $cfg['kesehatan']['employee_rate'],
            'kesehatan_company' => $kesehatanBase * $cfg['kesehatan']['company_rate'],
            'jht_employee' => $grossMonthlySalary * $cfg['jht']['employee_rate'],
            'jht_company' => $grossMonthlySalary * $cfg['jht']['company_rate'],
            'jp_employee' => $jpBase * $cfg['jp']['employee_rate'],
            'jp_company' => $jpBase * $cfg['jp']['company_rate'],
            'jkk_company' => $grossMonthlySalary * $cfg['jkk']['company_rate'],
            'jkm_company' => $grossMonthlySalary * $cfg['jkm']['company_rate'],
        ];
    }

    public function calculatePtkpAnnual(string $ptkpStatus): float
    {
        $cfg = config('payroll.pph21');

        $isMarried = str_starts_with($ptkpStatus, 'K');
        $dependents = (int) substr($ptkpStatus, -1);
        $dependents = min($dependents, $cfg['ptkp_max_dependents']);

        $ptkp = $cfg['ptkp_base'];
        if ($isMarried) {
            $ptkp += $cfg['ptkp_married_addition'];
        }
        $ptkp += $dependents * $cfg['ptkp_dependent_addition'];

        return $ptkp;
    }

    public function calculatePph21Monthly(float $grossMonthlySalary, float $bpjsEmployeeDeductible, string $ptkpStatus): float
    {
        $cfg = config('payroll.pph21');

        $grossAnnual = $grossMonthlySalary * 12;
        $bpjsDeductibleAnnual = $bpjsEmployeeDeductible * 12;

        $pkp = $this->taxablePkpAnnual($grossAnnual, $bpjsDeductibleAnnual, $ptkpStatus, $cfg);

        return $this->progressiveTaxAnnual($pkp, $cfg) / 12;
    }

    /**
     * THR (Tunjangan Hari Raya): mandatory annual religious-holiday bonus
     * (Permenaker No. 6/2016). An employee with >= 12 months of continuous
     * service gets a full month's wage; less than that (but >= 1 month) gets
     * it prorated by months of service / 12. THR is NOT part of the BPJS
     * wage base (only the fixed monthly wage is), so no BPJS is deducted
     * here.
     *
     * PPh21 on THR uses the incremental method DJP intends for irregular
     * income: tax owed on (this employee's regular annual income + THR)
     * minus tax owed on the regular annual income alone -- so THR is taxed
     * at the marginal bracket(s) it actually pushes the employee into,
     * rather than being taxed as if it were spread evenly across the year.
     * This mirrors calculatePph21Monthly's own annualized-bracket approach
     * rather than DJP's TER lookup tables -- see this class's own
     * disclaimer.
     */
    public function calculateThr(float $monthlySalary, int $monthsOfService, string $ptkpStatus): array
    {
        $monthsOfService = max(0, min(12, $monthsOfService));
        $grossThr = round($monthlySalary * ($monthsOfService / 12), 2);

        $pph21 = round($this->calculateThrPph21($monthlySalary, $grossThr, $ptkpStatus), 2);

        return [
            'months_of_service' => $monthsOfService,
            'gross_thr' => $grossThr,
            'pph21' => $pph21,
            'net_thr' => round($grossThr - $pph21, 2),
        ];
    }

    private function calculateThrPph21(float $monthlySalary, float $grossThr, string $ptkpStatus): float
    {
        if ($grossThr <= 0) {
            return 0.0;
        }

        $cfg = config('payroll.pph21');
        $bpjs = $this->calculateBpjs($monthlySalary);
        $bpjsDeductibleAnnual = ($bpjs['jht_employee'] + $bpjs['jp_employee']) * 12;
        $regularGrossAnnual = $monthlySalary * 12;

        $pkpWithoutThr = $this->taxablePkpAnnual($regularGrossAnnual, $bpjsDeductibleAnnual, $ptkpStatus, $cfg);
        $pkpWithThr = $this->taxablePkpAnnual($regularGrossAnnual + $grossThr, $bpjsDeductibleAnnual, $ptkpStatus, $cfg);

        return max(0, $this->progressiveTaxAnnual($pkpWithThr, $cfg) - $this->progressiveTaxAnnual($pkpWithoutThr, $cfg));
    }

    /**
     * PKP (Penghasilan Kena Pajak) Tahunan: gross annual income minus biaya
     * jabatan, BPJS JHT/JP employee contributions, and PTKP -- rounded down
     * to the nearest Rp 1,000 per Pasal 17 UU HPP.
     */
    private function taxablePkpAnnual(float $grossAnnual, float $bpjsDeductibleAnnual, string $ptkpStatus, array $cfg): float
    {
        $biayaJabatanAnnual = min($grossAnnual * $cfg['biaya_jabatan_rate'], $cfg['biaya_jabatan_monthly_cap'] * 12);
        $nettoAnnual = max(0, $grossAnnual - $biayaJabatanAnnual - $bpjsDeductibleAnnual);
        $ptkpAnnual = $this->calculatePtkpAnnual($ptkpStatus);

        return max(0, floor(($nettoAnnual - $ptkpAnnual) / 1000) * 1000);
    }

    /**
     * Applies the Pasal 17 UU HPP progressive brackets to an annual PKP.
     */
    private function progressiveTaxAnnual(float $pkp, array $cfg): float
    {
        $tax = 0;
        $previousCap = 0;

        foreach ($cfg['brackets'] as $bracket) {
            $bracketCeiling = $bracket['up_to'] ?? INF;
            $taxableInBracket = max(0, min($pkp, $bracketCeiling) - $previousCap);
            $tax += $taxableInBracket * $bracket['rate'];
            $previousCap = $bracketCeiling;

            if ($pkp <= $bracketCeiling) {
                break;
            }
        }

        return $tax;
    }
}
