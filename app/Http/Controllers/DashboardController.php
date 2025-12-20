<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\DashboardRepositoryInterface;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class DashboardController extends Controller implements HasMiddleware
{
    private DashboardRepositoryInterface $dashboardRepository;

    public function __construct(DashboardRepositoryInterface $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['dashboard-view']), only: ['getStatistics', 'getEmployeeStatistics']),
        ];
    }

    /**
     * Get dashboard statistics
     */
    public function getStatistics()
    {
        try {
            $statistics = $this->dashboardRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Dashboard Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getEmployeeStatistics()
    {
        try {
            $employeeId = auth()->user()->employeeProfile->id;
            $statistics = $this->dashboardRepository->getEmployeeStatistics($employeeId);

            return ResponseHelper::jsonResponse(true, 'Employee Dashboard Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
