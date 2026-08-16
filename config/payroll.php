<?php

/**
 * BPJS rates and PTKP/PPh21 bracket amounts are set by government regulation
 * and can change over time. Review these against the current regulation
 * (or a tax consultant) before relying on this for real payroll runs.
 */
return [

    'bpjs' => [
        'kesehatan' => [
            'employee_rate' => 0.01,
            'company_rate' => 0.04,
            'salary_cap' => 12000000,
        ],
        'jht' => [
            'employee_rate' => 0.02,
            'company_rate' => 0.037,
        ],
        'jp' => [
            'employee_rate' => 0.01,
            'company_rate' => 0.02,
            'salary_cap' => 10547400,
        ],
        'jkk' => [
            // Company only. Rate depends on the employer's work-risk class (0.24%-1.74%).
            'company_rate' => 0.0024,
        ],
        'jkm' => [
            'company_rate' => 0.003,
        ],
    ],

    'pph21' => [
        // Biaya jabatan: 5% of gross income, capped monthly.
        'biaya_jabatan_rate' => 0.05,
        'biaya_jabatan_monthly_cap' => 500000,

        // Annual PTKP (Penghasilan Tidak Kena Pajak) base and increments (UU HPP).
        'ptkp_base' => 54000000,
        'ptkp_married_addition' => 4500000,
        'ptkp_dependent_addition' => 4500000,
        'ptkp_max_dependents' => 3,

        // Progressive brackets (Pasal 17 UU HPP), applied to annualized taxable income.
        'brackets' => [
            ['up_to' => 60000000, 'rate' => 0.05],
            ['up_to' => 250000000, 'rate' => 0.15],
            ['up_to' => 500000000, 'rate' => 0.25],
            ['up_to' => 5000000000, 'rate' => 0.30],
            ['up_to' => null, 'rate' => 0.35],
        ],
    ],

];
