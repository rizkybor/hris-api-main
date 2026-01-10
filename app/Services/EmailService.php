<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\InfrastructureTool;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestCreated;
use App\Notifications\LeaveRequestRejected;
use App\Notifications\PayrollPaid;
use App\Notifications\InfrastructureToolReminderNotification;

use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;


class EmailService
{
    
    /**
     * Send leave request created notification
     */
    public function sendLeaveRequestCreatedNotification(LeaveRequest $leaveRequest): void
    {
        $user = $leaveRequest->employee?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new LeaveRequestCreated($leaveRequest));
    }

    /**
     * Send leave request approved notification
     */
    public function sendLeaveRequestApprovedNotification(LeaveRequest $leaveRequest): void
    {
        $user = $leaveRequest->employee?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new LeaveRequestApproved($leaveRequest));
    }

    /**
     * Send leave request rejected notification
     */
    public function sendLeaveRequestRejectedNotification(LeaveRequest $leaveRequest): void
    {
        $user = $leaveRequest->employee?->user;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new LeaveRequestRejected($leaveRequest));
    }

    public function sendPayrollPaidNotifications(int $payrollId): void
    {
        Payroll::findOrFail($payrollId);

        $payrollDetails = PayrollDetail::where('payroll_id', $payrollId)
            ->with('employee.user')
            ->take(10)
            ->get();

        foreach ($payrollDetails as $payrollDetail) {
            $user = $payrollDetail->employee?->user;

            if (! $user || ! $user->email) {
                continue;
            }

            $user->notify(new PayrollPaid($payrollDetail));
        }
    }

    // ================= NEW METHOD =================
    /**
     * Send reminder email for infrastructure tools expiring in X days.
     *
     * @param int $daysBeforeExpired Number of days before expired date, default 5
     * @param string $recipient Email recipient
     */
   public function sendInfrastructureToolReminder(int $daysBeforeExpired = 5, string $recipient = 'contact@jcdigital.co.id'): void
    {
        $targetDate = Carbon::now()->addDays($daysBeforeExpired)->toDateString();

        $tools = InfrastructureTool::whereDate('expired_date', $targetDate)->get();

        if ($tools->isEmpty()) {
            return;
        }

        // Send notification
        Notification::route('mail', $recipient)
            ->notify(new InfrastructureToolReminderNotification($tools->toArray(), $targetDate));
    }
}
