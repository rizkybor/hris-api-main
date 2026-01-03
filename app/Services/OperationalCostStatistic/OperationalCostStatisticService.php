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
     * Get aggregated operational cost statistics with optional search filter
     *
     * @param string|null $search
     * @return array
     */
    public function getStatistic(?string $search = null): array
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS . ($search ?? 'all');

        return Cache::remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($search) {

            // Ambil saldo company terbaru
            $companyBalance = (float) ($this->financeRepo->first()?->saldo_company ?? 0);

 // Logging untuk debug
        Log::info('OperationalCostStatisticService::getStatistic called', [
            'search' => $search,
            'company_balance' => $companyBalance,
            'fixed_cost_count' => $this->fixedCostRepo->getStatistic($search)['items']->count(),
            'sdm_resource_count' => $this->sdmRepo->getStatistic($search)['items']->count(),
            'infrastructure_count' => $this->infraRepo->getStatistic($search)['items']->count(),
        ]);

            return [
                'fixed_cost' => $this->fixedCostRepo->getStatistic($search),
                'sdm_resource' => $this->sdmRepo->getStatistic($search),
                'infrastructure' => $this->infraRepo->getStatistic($search),
                'company_balance' => number_format($companyBalance, 2, '.', ''),
            ];
        });
    }
}
