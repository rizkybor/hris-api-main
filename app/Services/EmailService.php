<?php

namespace App\Services;

use App\Models\DocumentLetter;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\InfrastructureTool;
use App\Models\User;
use App\Notifications\DocumentLetterApproved;
use App\Notifications\DocumentLetterRejected;
use App\Notifications\DocumentLetterSubmitted;
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

    /**
     * Notify every Finance Manager account that an Official Memo is waiting
     * for their approval.
     */
    public function sendDocumentLetterSubmittedNotification(DocumentLetter $documentLetter): void
    {
        $financeManagers = User::role('finance')->get();

        if ($financeManagers->isEmpty()) {
            return;
        }

        Notification::send($financeManagers, new DocumentLetterSubmitted($documentLetter));
    }

    /**
     * Notify the document's author once a Finance Manager approves it.
     */
    public function sendDocumentLetterApprovedNotification(DocumentLetter $documentLetter): void
    {
        $user = $documentLetter->creator;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new DocumentLetterApproved($documentLetter));
    }

    /**
     * Notify the document's author once a Finance Manager rejects it.
     */
    public function sendDocumentLetterRejectedNotification(DocumentLetter $documentLetter): void
    {
        $user = $documentLetter->creator;

        if (! $user || ! $user->email) {
            return;
        }

        $user->notify(new DocumentLetterRejected($documentLetter));
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
