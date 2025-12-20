<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestCreated;
use App\Notifications\LeaveRequestRejected;
use App\Notifications\PayrollPaid;

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
}
