<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\FilesCompanyController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\FixedCostController;
use App\Http\Controllers\InfrastructureToolController;
use App\Http\Controllers\CompanyFinanceController;
use App\Http\Controllers\SdmResourceController;
use App\Http\Controllers\CompanyAboutController;
use App\Http\Controllers\VendorsController;
use App\Http\Controllers\VendorsAttachmentController;
use App\Http\Controllers\VendorsTaskListController;
use App\Http\Controllers\VendorsTaskPaymentController;
use App\Http\Controllers\VendorsTaskPivotController;
use App\Http\Controllers\VendorsTaskScopeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->group(function () {

        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);

            Route::post('logout', [AuthController::class, 'logout']);

            Route::get('teams/statistics', [TeamController::class, 'getStatistics']);
            Route::get('teams/all/paginated', [TeamController::class, 'getAllPaginated']);
            Route::get('teams/{team}/statistics', [TeamController::class, 'getTeamStatistics']);
            Route::get('teams/{team}/chart-data', [TeamController::class, 'getTeamChartData']);
            Route::post('teams/{team}/add-member', [TeamController::class, 'addMember']);
            Route::post('teams/{team}/remove-member', [TeamController::class, 'removeMember']);
            Route::apiResource('teams', TeamController::class);

            Route::get('my-profile', [EmployeeProfileController::class, 'getMyProfile']);
            Route::get('my-team', [EmployeeProfileController::class, 'getMyTeam']);
            Route::get('my-team/members', [EmployeeProfileController::class, 'getMyTeamMembers']);
            Route::get('my-team/projects', [EmployeeProfileController::class, 'getMyTeamProjects']);
            Route::get('employees/statistics', [EmployeeProfileController::class, 'getStatistics']);
            Route::get('employees/{id}/performance-statistics', [EmployeeProfileController::class, 'getPerformanceStatistics']);
            Route::get('employees/all/paginated', [EmployeeProfileController::class, 'getAllPaginated']);
            Route::apiResource('employees', EmployeeProfileController::class);

            Route::get('projects/statistics', [ProjectController::class, 'getStatistics']);
            Route::get('projects/all/paginated', [ProjectController::class, 'getAllPaginated']);
            Route::apiResource('projects', ProjectController::class);

            Route::apiResource('project-tasks', ProjectTaskController::class);
            Route::get('project-tasks/all/paginated', [ProjectTaskController::class, 'getAllPaginated']);

            Route::get('attendances/all/paginated', [AttendanceController::class, 'getAllPaginated']);
            Route::get('attendances/statistics', [AttendanceController::class, 'getStatistics']);
            Route::get('my-attendances', [AttendanceController::class, 'getMyAttendances']);
            Route::get('my-attendance-statistics', [AttendanceController::class, 'getMyAttendanceStatistics']);
            Route::get('attendances/last-attendance', [AttendanceController::class, 'getLastAttendance']);
            Route::post('attendances/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('attendances/check-out', [AttendanceController::class, 'checkOut']);
            Route::apiResource('attendances', AttendanceController::class);

            Route::apiResource('leave-requests', LeaveRequestController::class);
            Route::get('leave-requests/all/paginated', [LeaveRequestController::class, 'getAllPaginated']);
            Route::get('my-leave-requests', [LeaveRequestController::class, 'getMyLeaveRequests']);
            Route::post('leave-requests/approve/{id}', [LeaveRequestController::class, 'approve']);
            Route::post('leave-requests/reject/{id}', [LeaveRequestController::class, 'reject']);

            // Payroll routes
            Route::get('payrolls/statistics', [PayrollController::class, 'getStatistics']);
            Route::get('payrolls/all/paginated', [PayrollController::class, 'getAllPaginated']);
            Route::post('payrolls/generate', [PayrollController::class, 'generate']);
            Route::get('payrolls/{id}/statistics', [PayrollController::class, 'getPayrollStatistics']);
            Route::get('payrolls/{id}/details', [PayrollController::class, 'getDetails']); // Paginated details
            Route::get('payrolls/{id}/export-excel', [PayrollController::class, 'exportExcel']);
            Route::post('payrolls/{id}/mark-as-paid', [PayrollController::class, 'markAsPaid']);
            Route::put('payroll-details/{id}', [PayrollController::class, 'updateDetail']);
            Route::apiResource('payrolls', PayrollController::class)->only(['index', 'show']);

            // Options routes
            Route::get('options/departments', [OptionController::class, 'getDepartments']);
            Route::get('options/employment-types', [OptionController::class, 'getEmploymentTypes']);
            Route::get('options/job-statuses', [OptionController::class, 'getJobStatuses']);
            Route::get('options/task-priorities', [OptionController::class, 'getTaskPriorities']);
            Route::get('options/task-statuses', [OptionController::class, 'getTaskStatuses']);
            Route::get('options/leave-types', [OptionController::class, 'getLeaveTypes']);
            Route::get('options/work-locations', [OptionController::class, 'getWorkLocations']);
            Route::get('options/skill-levels', [OptionController::class, 'getSkillLevels']);

            // Dashboard routes
            Route::get('dashboard/statistics', [DashboardController::class, 'getStatistics']);
            Route::get('dashboard/my-statistics', [DashboardController::class, 'getEmployeeStatistics']);

            // Credential Account routes
            Route::get('credential-accounts/all/paginated', [CredentialAccountController::class, 'getAllPaginated']);
            Route::apiResource('credential-accounts', CredentialAccountController::class);

            // Files Company routes
            Route::get('files-companies/all/paginated', [FilesCompanyController::class, 'getAllPaginated']);
            Route::apiResource('files-companies', FilesCompanyController::class);

            // Fixed Cost routes
            Route::get('fixed-costs/all/paginated', [FixedCostController::class, 'getAllPaginated']);
            Route::get('fixed-costs/statistic', [FixedCostController::class, 'getStatistic']);
            Route::apiResource('fixed-costs', FixedCostController::class);

            // Infrastructure Tools routes
            Route::get('infrastructure-tools/all/paginated', [InfrastructureToolController::class, 'getAllPaginated']);
            Route::apiResource('infrastructure-tools', InfrastructureToolController::class);

            // Company Finance routes
            Route::get('company-finances/all/paginated', [CompanyFinanceController::class, 'getAllPaginated']);
            // Endpoint statistic
            Route::get('company-finances/statistic', [CompanyFinanceController::class, 'getStatistic']);
            Route::apiResource('company-finances', CompanyFinanceController::class);

            // Sdm Resources routes
            Route::get('sdm-resources/all/paginated', [SdmResourceController::class, 'getAllPaginated']);
            Route::apiResource('sdm-resources', SdmResourceController::class);

            // Company About
            Route::apiResource('company-about', CompanyAboutController::class);

            // Vendors
            Route::get('vendors/all/paginated', [VendorsController::class, 'getAllPaginated']);
            Route::apiResource('vendors', VendorsController::class);

            // Vendors Attachment
            Route::get('vendors-attachment/all/paginated', [VendorsAttachmentController::class, 'getAllPaginated']);
            Route::apiResource('vendors-attachment', VendorsAttachmentController::class);

            // Vendors Task List
            Route::get('vendors-task-list/all/paginated', [VendorsTaskListController::class, 'getAllPaginated']);
            Route::apiResource('vendors-task-list', VendorsTaskListController::class);

            // Vendors Task Payment
            Route::get('vendors-task-payment/all/paginated', [VendorsTaskPaymentController::class, 'getAllPaginated']);
            Route::apiResource('vendors-task-payment', VendorsTaskPaymentController::class);

            // Vendors Task Scope
            Route::get('vendors-task-scope/all/paginated', [VendorsTaskScopeController::class, 'getAllPaginated']);
            Route::apiResource('vendors-task-scope', VendorsTaskScopeController::class);

            // Vendors Task Pivot
            Route::get('vendors-task-pivot/all/paginated', [VendorsTaskPivotController::class, 'getAllPaginated']);
            Route::apiResource('vendors-task-pivot', VendorsTaskPivotController::class);
        });
    });
