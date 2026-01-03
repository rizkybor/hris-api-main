<?php

namespace App\Services\OperationalCostStatistic;

use App\Repositories\FixedCostRepository;
use App\Repositories\SdmResourceRepository;
use App\Repositories\InfrastructureToolRepository;
use App\Repositories\CompanyFinanceRepository;
use App\Constants\CacheConstants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OperationalCostStatisticService
{
    public function __construct(
        protected FixedCostRepository $fixedCostRepo,
        protected SdmResourceRepository $sdmRepo,
        protected InfrastructureToolRepository $infraRepo,
        protected CompanyFinanceRepository $financeRepo,
    ) {}

    /**
     * Get aggregated operational cost statistics (no search)
     *
     * @return array
     */
    public function getStatistic(): array
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS . '_all';

        return Cache::remember($cacheKey, CacheConstants::ONE_HOUR, function () {

            // Ambil semua data tanpa filter search
            $fixedCostData = $this->fixedCostRepo->getStatistic(null); // pastikan repository menangani null = ambil semua
            $sdmData = $this->sdmRepo->getStatistic(null);
            $infraData = $this->infraRepo->getStatistic(null);

            // Ambil saldo company terbaru
            $companyBalance = (float) ($this->financeRepo->first()?->saldo_company ?? 0);

            // Logging lengkap untuk debug
            Log::info('OperationalCostStatisticService::getStatistic called', [
                'fixed_cost' => [
                    'count' => count($fixedCostData['items']),
                    'summary' => $fixedCostData['summary'],
                ],
                'sdm_resource' => [
                    'count' => count($sdmData['items']),
                    'summary' => $sdmData['summary'],
                ],
                'infrastructure' => [
                    'count' => count($infraData['items']),
                    'summary' => $infraData['summary'],
                ],
                'company_balance' => $companyBalance,
            ]);

            return [
                'fixed_cost' => $fixedCostData,
                'sdm_resource' => $sdmData,
                'infrastructure' => $infraData,
                'company_balance' => number_format($companyBalance, 2, '.', ''),
            ];
        });
    }
}
