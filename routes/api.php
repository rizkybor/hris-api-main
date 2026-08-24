<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CredentialAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisionCodeController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LetterCodeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\DocumentLetterController;
use App\Http\Controllers\MeetingNoteCommentController;
use App\Http\Controllers\MeetingNoteController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CertificateSettingController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\EmployeeFileController;
use App\Http\Controllers\FilesCompanyController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\LandingPageRateSettingController;
use App\Http\Controllers\ProjectCalculationController;
use App\Http\Controllers\ProjectRateSettingController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectTaskCommentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ConfigurableOptionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StaffPermissionController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CompanyAssetController;
use App\Http\Controllers\EmployeeResignationController;
use App\Http\Controllers\PerformanceReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\FixedCostController;
use App\Http\Controllers\InfrastructureToolController;
use App\Http\Controllers\CompanyFinanceController;
use App\Http\Controllers\SdmResourceController;
use App\Http\Controllers\SdmFieldController;
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

        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);

            Route::post('logout', [AuthController::class, 'logout']);

            Route::get('teams/statistics', [TeamController::class, 'getStatistics']);
            Route::get('teams/org-chart', [TeamController::class, 'getOrgChart']);
            Route::get('teams/all/paginated', [TeamController::class, 'getAllPaginated']);
            Route::get('teams/{team}/statistics', [TeamController::class, 'getTeamStatistics']);
            Route::get('teams/{team}/chart-data', [TeamController::class, 'getTeamChartData']);
            Route::post('teams/{team}/add-member', [TeamController::class, 'addMember']);
            Route::post('teams/{team}/remove-member', [TeamController::class, 'removeMember']);
            Route::apiResource('teams', TeamController::class);

            Route::get('my-profile', [EmployeeProfileController::class, 'getMyProfile']);
            Route::get('my-profile/id-card', [EmployeeProfileController::class, 'downloadIdCard']);
            Route::get('my-team', [EmployeeProfileController::class, 'getMyTeam']);
            Route::get('my-team/members', [EmployeeProfileController::class, 'getMyTeamMembers']);
            Route::get('my-team/projects', [EmployeeProfileController::class, 'getMyTeamProjects']);
            Route::get('employees/statistics', [EmployeeProfileController::class, 'getStatistics']);
            Route::get('employees/contract-alerts', [EmployeeProfileController::class, 'getContractAlerts']);
            Route::get('employees/{id}/performance-statistics', [EmployeeProfileController::class, 'getPerformanceStatistics']);
            Route::get('employees/all/paginated', [EmployeeProfileController::class, 'getAllPaginated']);
            Route::apiResource('employees', EmployeeProfileController::class);

            Route::get('employees/{employeeId}/files', [EmployeeFileController::class, 'index']);
            Route::post('employees/{employeeId}/files', [EmployeeFileController::class, 'store']);
            Route::delete('employee-files/{id}', [EmployeeFileController::class, 'destroy']);

            Route::get('projects/statistics', [ProjectController::class, 'getStatistics']);
            Route::get('projects/all/paginated', [ProjectController::class, 'getAllPaginated']);
            Route::get('projects/{id}/export-progress', [ProjectController::class, 'exportProgress']);
            Route::apiResource('projects', ProjectController::class);

            Route::apiResource('project-tasks', ProjectTaskController::class);
            Route::get('project-tasks/all/paginated', [ProjectTaskController::class, 'getAllPaginated']);
            Route::get('my-tasks', [ProjectTaskController::class, 'getMyTasks']);

            Route::get('project-tasks/{taskId}/comments', [ProjectTaskCommentController::class, 'index']);
            Route::post('project-tasks/{taskId}/comments', [ProjectTaskCommentController::class, 'store']);
            Route::delete('project-task-comments/{id}', [ProjectTaskCommentController::class, 'destroy']);

            // Project Documents
            Route::apiResource('project-documents', ProjectDocumentController::class);

            // Project Calculator
            Route::get('project-calculator/rate-setting', [ProjectRateSettingController::class, 'show']);
            Route::put('project-calculator/rate-setting', [ProjectRateSettingController::class, 'update']);
            Route::get('project-calculator/landing-page-setting', [LandingPageRateSettingController::class, 'show']);
            Route::put('project-calculator/landing-page-setting', [LandingPageRateSettingController::class, 'update']);
            Route::get('project-calculations/statistics', [ProjectCalculationController::class, 'getStatistics']);
            Route::post('project-calculations/preview', [ProjectCalculationController::class, 'preview']);
            Route::apiResource('project-calculations', ProjectCalculationController::class);

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
            Route::get('leave-requests/balance/my', [LeaveRequestController::class, 'getMyLeaveBalance']);
            Route::get('leave-requests/balance/{employeeId}', [LeaveRequestController::class, 'getLeaveBalance']);

            // Payroll routes
            Route::get('payrolls/statistics', [PayrollController::class, 'getStatistics']);
            Route::get('payrolls/all/paginated', [PayrollController::class, 'getAllPaginated']);
            Route::post('payrolls/generate', [PayrollController::class, 'generate']);
            Route::get('payrolls/{id}/statistics', [PayrollController::class, 'getPayrollStatistics']);
            Route::get('payrolls/{id}/details', [PayrollController::class, 'getDetails']); // Paginated details
            Route::get('payrolls/{id}/positions', [PayrollController::class, 'getPositions']);
            Route::get('payrolls/{id}/export-excel', [PayrollController::class, 'exportExcel']);
            Route::post('payrolls/{id}/mark-as-paid', [PayrollController::class, 'markAsPaid']);
            Route::delete('payrolls/{id}', [PayrollController::class, 'destroy']);
            Route::put('payroll-details/{id}', [PayrollController::class, 'updateDetail']);
            Route::apiResource('payrolls', PayrollController::class)->only(['index', 'show']);

            // My Payslips routes
            Route::get('my-payslips', [PayslipController::class, 'index']);
            Route::get('my-payslips/{id}', [PayslipController::class, 'show']);
            Route::get('payslips/{id}/download', [PayslipController::class, 'download']);

            // Options routes
            Route::get('options/departments', [OptionController::class, 'getDepartments']);
            Route::get('options/employment-types', [OptionController::class, 'getEmploymentTypes']);
            Route::get('options/job-statuses', [OptionController::class, 'getJobStatuses']);
            Route::get('options/task-priorities', [OptionController::class, 'getTaskPriorities']);
            Route::get('options/task-statuses', [OptionController::class, 'getTaskStatuses']);
            Route::get('options/leave-types', [OptionController::class, 'getLeaveTypes']);
            Route::get('options/work-locations', [OptionController::class, 'getWorkLocations']);
            Route::get('options/skill-levels', [OptionController::class, 'getSkillLevels']);
            Route::get('options/ptkp-statuses', [OptionController::class, 'getPtkpStatuses']);
            Route::get('options/bank-names', [OptionController::class, 'getBankNames']);
            Route::get('options/preferred-languages', [OptionController::class, 'getPreferredLanguages']);
            Route::get('options/pph-types', [OptionController::class, 'getPphTypes']);

            // Dashboard routes
            Route::get('dashboard/statistics', [DashboardController::class, 'getStatistics']);
            Route::get('dashboard/my-statistics', [DashboardController::class, 'getEmployeeStatistics']);

            // Credential Account routes
            Route::get('credential-accounts/all/paginated', [CredentialAccountController::class, 'getAllPaginated']);
            Route::apiResource('credential-accounts', CredentialAccountController::class);

            // Files Company routes
            Route::get('files-companies/all/paginated', [FilesCompanyController::class, 'getAllPaginated']);
            Route::get('files-companies/statistics', [FilesCompanyController::class, 'statistics']); // route statistics
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
            Route::apiResource('sdm-fields', SdmFieldController::class)->except(['show']);

            // Company About
            Route::apiResource('company-about', CompanyAboutController::class);

            // Vendors
            Route::get('vendors/all/paginated', [VendorsController::class, 'getAllPaginated']);
            Route::get('vendors/statistic', [VendorsController::class, 'getStatistic']);
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

            // Reports
            Route::get('reports/attendance', [ReportController::class, 'attendance']);
            Route::get('reports/payroll', [ReportController::class, 'payroll']);
            Route::get('reports/employee', [ReportController::class, 'employee']);
            Route::get('reports/finance', [ReportController::class, 'finance']);
            Route::get('reports/pph21', [ReportController::class, 'pph21']);
            Route::get('reports/ppn', [ReportController::class, 'ppn']);
            Route::get('reports/export', [ReportController::class, 'export']);

            // Settings: Roles & Permissions
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::apiResource('roles', RoleController::class);

            // Settings: Configurable Dropdown Options
            Route::apiResource('configurable-options', ConfigurableOptionController::class)->except(['show']);

            // Settings: Per-account Staff Permissions
            Route::get('staff-accounts', [StaffPermissionController::class, 'index']);
            Route::get('staff-accounts/{employee}/permissions', [StaffPermissionController::class, 'show']);
            Route::put('staff-accounts/{employee}/permissions', [StaffPermissionController::class, 'update']);

            // Settings: Database Backup
            Route::get('backups', [BackupController::class, 'index']);
            // A full DB dump (every table, chunked reads) is heavy enough
            // that letting it be triggered without limit is a DoS vector on
            // its own, independent of anyone's login credentials.
            Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:3,1');
            Route::get('backups/{id}/download', [BackupController::class, 'download']);
            Route::delete('backups/{id}', [BackupController::class, 'destroy']);

            // History / Activity Log
            Route::get('history', [ActivityLogController::class, 'index']);
            Route::get('history/categories', [ActivityLogController::class, 'categories']);
            Route::get('history/statistics', [ActivityLogController::class, 'statistics']);

            // Announcements
            Route::apiResource('announcements', AnnouncementController::class)->except(['show']);
            Route::get('announcements/{id}', [AnnouncementController::class, 'show']);

            // Company Assets
            Route::get('my-assets', [CompanyAssetController::class, 'myAssets']);
            Route::get('company-assets/statistics', [CompanyAssetController::class, 'statistics']);
            Route::post('company-assets/{id}/assign', [CompanyAssetController::class, 'assign']);
            Route::post('company-assets/{id}/return', [CompanyAssetController::class, 'returnAsset']);
            Route::apiResource('company-assets', CompanyAssetController::class)->except(['show']);
            Route::get('company-assets/{id}', [CompanyAssetController::class, 'show']);

            // Resignation / Offboarding
            Route::get('resignations', [EmployeeResignationController::class, 'index']);
            Route::get('employees/{employeeId}/resignation', [EmployeeResignationController::class, 'show']);
            Route::post('employees/{employeeId}/resignation', [EmployeeResignationController::class, 'store']);
            Route::post('resignations/{id}/complete', [EmployeeResignationController::class, 'complete']);

            // Performance Reviews
            Route::get('my-performance-reviews', [PerformanceReviewController::class, 'myReviews']);
            Route::post('performance-reviews/{id}/acknowledge', [PerformanceReviewController::class, 'acknowledge']);
            Route::apiResource('performance-reviews', PerformanceReviewController::class);

            // Notifications
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

            // Global search
            Route::get('search', [SearchController::class, 'search']);

            // =====================================================================
            // Document Letters (frontend "Document Letters" menu / DocumentsHub.vue)
            // Sub-modules below are ordered to match the hub's card order:
            // Surat-Surat, Invoice, Payment Receipt, Purchase Order, Official
            // Memo, Meeting Note, Sertifikat.
            // =====================================================================

            // Document Letters: Letters (Surat)
            Route::get('letters/{id}/export-pdf', [LetterController::class, 'exportPdf']);
            Route::post('letters/{id}/cancel', [LetterController::class, 'cancel']);
            Route::apiResource('letters', LetterController::class);

            // Document Letters: Letters reference tables (Letter Codes, Division Codes)
            Route::apiResource('letter-codes', LetterCodeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('division-codes', DivisionCodeController::class)->only(['index', 'store', 'update', 'destroy']);

            // Document Letters: Invoices
            Route::get('invoices/{id}/export-pdf', [InvoiceController::class, 'exportPdf']);
            Route::post('invoices/{id}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
            Route::post('invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
            Route::apiResource('invoices', InvoiceController::class);

            // Document Letters: Invoices reference table (Bank Accounts for Payment & Tax)
            Route::apiResource('bank-accounts', BankAccountController::class)->only(['index', 'store', 'update', 'destroy']);

            // Document Letters: Payment Receipts
            Route::get('payment-receipts/{id}/export-pdf', [PaymentReceiptController::class, 'exportPdf']);
            Route::post('payment-receipts/{id}/cancel', [PaymentReceiptController::class, 'cancel']);
            Route::apiResource('payment-receipts', PaymentReceiptController::class);

            // Document Letters: Purchase Orders
            Route::get('purchase-orders/{id}/export-pdf', [PurchaseOrderController::class, 'exportPdf']);
            Route::post('purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
            Route::apiResource('purchase-orders', PurchaseOrderController::class);

            // Document Letters: Official Memo (Nota Dinas) -- approval workflow,
            // recipient is always Finance Manager (the sole approver)
            Route::post('document-letters/{id}/submit', [DocumentLetterController::class, 'submit']);
            Route::post('document-letters/{id}/approve', [DocumentLetterController::class, 'approve']);
            Route::post('document-letters/{id}/reject', [DocumentLetterController::class, 'reject']);
            Route::get('document-letters/{id}/export-pdf', [DocumentLetterController::class, 'exportPdf']);
            Route::apiResource('document-letters', DocumentLetterController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

            // Document Letters: Meeting Note -- shared repository restricted to
            // Manager/Operational Director/HR/Finance Manager, with per-user
            // pin-to-dashboard and polling-based presence
            Route::get('meeting-notes/pinned', [MeetingNoteController::class, 'pinned']);
            Route::post('meeting-notes/{id}/pin', [MeetingNoteController::class, 'togglePin']);
            Route::post('meeting-notes/{id}/heartbeat', [MeetingNoteController::class, 'heartbeat']);
            Route::get('meeting-notes/{id}/viewers', [MeetingNoteController::class, 'viewers']);
            Route::apiResource('meeting-notes', MeetingNoteController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::get('meeting-notes/{meetingNoteId}/comments', [MeetingNoteCommentController::class, 'index']);
            Route::post('meeting-notes/{meetingNoteId}/comments', [MeetingNoteCommentController::class, 'store']);
            Route::delete('meeting-note-comments/{id}', [MeetingNoteCommentController::class, 'destroy']);

            // Document Letters: Certificates
            Route::get('certificate-setting', [CertificateSettingController::class, 'show']);
            Route::put('certificate-setting', [CertificateSettingController::class, 'update']);
            Route::apiResource('certificate-templates', CertificateTemplateController::class)->only(['index', 'store', 'destroy']);
            Route::get('certificates/statistics', [CertificateController::class, 'getStatistics']);
            Route::post('certificates/preview-number', [CertificateController::class, 'previewNumber']);
            // PDF rendering (and ZIP bundling for bulk requests up to 500
            // recipients) is CPU/IO-heavy enough to be a DoS vector if it
            // can be fired without limit; 10/min is generous for legitimate
            // bulk-generation usage while still bounding the worst case.
            Route::post('certificates/generate', [CertificateController::class, 'generate'])->middleware('throttle:10,1');
            Route::get('certificates/{id}/download', [CertificateController::class, 'download']);
            Route::apiResource('certificates', CertificateController::class)->only(['index', 'show', 'destroy']);
        });
    });
