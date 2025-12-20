<?php

namespace App\Providers;

use App\Interfaces\AttendanceRepositoryInterface;
use App\Interfaces\AuthRepositoryInterface;
use App\Interfaces\BankInformationRepositoryInterface;
use App\Interfaces\DashboardRepositoryInterface;
use App\Interfaces\EmergencyContactRepositoryInterface;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Interfaces\JobInformationRepositoryInterface;
use App\Interfaces\LeaveRequestRepositoryInterface;
use App\Interfaces\PayrollRepositoryInterface;
use App\Interfaces\ProjectRepositoryInterface;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Interfaces\TeamRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\AttendanceRepository;
use App\Repositories\AuthRepository;
use App\Repositories\BankInformationRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EmergencyContactRepository;
use App\Repositories\EmployeeProfileRepository;
use App\Repositories\JobInformationRepository;
use App\Repositories\LeaveRequestRepository;
use App\Repositories\PayrollRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectTaskRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(JobInformationRepositoryInterface::class, JobInformationRepository::class);
        $this->app->bind(BankInformationRepositoryInterface::class, BankInformationRepository::class);
        $this->app->bind(EmergencyContactRepositoryInterface::class, EmergencyContactRepository::class);
        $this->app->bind(EmployeeProfileRepositoryInterface::class, EmployeeProfileRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(ProjectTaskRepositoryInterface::class, ProjectTaskRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(LeaveRequestRepositoryInterface::class, LeaveRequestRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
