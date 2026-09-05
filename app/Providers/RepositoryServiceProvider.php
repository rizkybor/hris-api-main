<?php

namespace App\Providers;

use App\Interfaces\AttendanceRepositoryInterface;
use App\Interfaces\AuthRepositoryInterface;
use App\Interfaces\BankInformationRepositoryInterface;
use App\Interfaces\CompanyAboutRepositoryInterface;
use App\Interfaces\CredentialAccountRepositoryInterface;
use App\Interfaces\DashboardRepositoryInterface;
use App\Interfaces\EmergencyContactRepositoryInterface;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Interfaces\FilesCompanyRepositoryInterface;
use App\Interfaces\JobInformationRepositoryInterface;
use App\Interfaces\LeaveRequestRepositoryInterface;
use App\Interfaces\PayrollRepositoryInterface;
use App\Interfaces\ProjectDocumentRepositoryInterface;
use App\Interfaces\ProjectRepositoryInterface;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Interfaces\ReportRepositoryInterface;
use App\Interfaces\TeamRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\CompanyFinanceRepositoryInterface;
use App\Interfaces\FixedCostRepositoryInterface;
use App\Interfaces\InfrastructureToolRepositoryInterface;
use App\Interfaces\SdmResourceRepositoryInterface;
use App\Interfaces\ClientEvaluationRepositoryInterface;
use App\Interfaces\ClientAttachmentRepositoryInterface;
use App\Interfaces\ClientRepositoryInterface;
use App\Interfaces\ClientTaskListRepositoryInterface;
use App\Interfaces\ClientTaskPaymentRepositoryInterface;
use App\Interfaces\ClientTaskPivotRepositoryInterface;
use App\Interfaces\ClientTaskScopeRepositoryInterface;

use App\Repositories\AttendanceRepository;
use App\Repositories\AuthRepository;
use App\Repositories\BankInformationRepository;
use App\Repositories\CompanyAboutRepository;
use App\Repositories\CredentialAccountRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EmergencyContactRepository;
use App\Repositories\EmployeeProfileRepository;
use App\Repositories\FilesCompanyRepository;
use App\Repositories\JobInformationRepository;
use App\Repositories\LeaveRequestRepository;
use App\Repositories\PayrollRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectDocumentRepository;
use App\Repositories\ProjectTaskRepository;
use App\Repositories\ReportRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use App\Repositories\CompanyFinanceRepository;
use App\Repositories\FixedCostRepository;
use App\Repositories\InfrastructureToolRepository;
use App\Repositories\SdmResourceRepository;
use App\Repositories\ClientAttachmentRepository;
use App\Repositories\ClientEvaluationRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ClientTaskListRepository;
use App\Repositories\ClientTaskPaymentRepository;
use App\Repositories\ClientTaskPivotRepository;
use App\Repositories\ClientTaskScopeRepository;

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
        $this->app->bind(CredentialAccountRepositoryInterface::class, CredentialAccountRepository::class);
        $this->app->bind(FilesCompanyRepositoryInterface::class, FilesCompanyRepository::class);
        $this->app->bind(CompanyFinanceRepositoryInterface::class, CompanyFinanceRepository::class);
        $this->app->bind(FixedCostRepositoryInterface::class, FixedCostRepository::class);
        $this->app->bind(InfrastructureToolRepositoryInterface::class, InfrastructureToolRepository::class);
        $this->app->bind(SdmResourceRepositoryInterface::class, SdmResourceRepository::class);
        $this->app->bind(CompanyAboutRepositoryInterface::class, CompanyAboutRepository::class);
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
        $this->app->bind(ProjectDocumentRepositoryInterface::class, ProjectDocumentRepository::class);

        // Client
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(ClientAttachmentRepositoryInterface::class, ClientAttachmentRepository::class);
        $this->app->bind(ClientEvaluationRepositoryInterface::class, ClientEvaluationRepository::class);
        $this->app->bind(ClientTaskListRepositoryInterface::class, ClientTaskListRepository::class);
        $this->app->bind(ClientTaskPaymentRepositoryInterface::class, ClientTaskPaymentRepository::class);
        $this->app->bind(ClientTaskScopeRepositoryInterface::class, ClientTaskScopeRepository::class);
        $this->app->bind(ClientTaskPivotRepositoryInterface::class, ClientTaskPivotRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
