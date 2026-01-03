<?php

namespace App\Services\OperationalCostStatistic;

use App\Repositories\FixedCostRepository;
use App\Repositories\SdmResourceRepository;
use App\Repositories\InfrastructureToolRepository;
use App\Repositories\CompanyFinanceRepository;

class OperationalCostStatisticService
{
    public function __construct(
        protected FixedCostRepository $fixedCostRepo,
        protected SdmResourceRepository $sdmRepo,
        protected InfrastructureToolRepository $infraRepo,
        protected CompanyFinanceRepository $financeRepo,
    ) {}

    /**
     * Get aggregated operational cost statistics (no cache, no search filter)
     *
     * @return array
     */
    public function getStatistic(): array
    {
        // Ambil semua data tanpa filter search
        $fixedCostData = $this->fixedCostRepo->getStatistic(null); // null = ambil semua
        $sdmData = $this->sdmRepo->getStatistic(null);
        $infraData = $this->infraRepo->getStatistic(null);

        // Ambil saldo company terbaru
        $companyBalance = (float) ($this->financeRepo->first()?->saldo_company ?? 0);

        return [
            'fixed_cost' => $fixedCostData,
            'sdm_resource' => $sdmData,
            'infrastructure' => $infraData,
            'company_balance' => number_format($companyBalance, 2, '.', ''),
        ];
    }
}
