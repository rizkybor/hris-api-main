<?php

namespace App\Notifications;

use App\Models\PayrollDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollPaid extends Notification implements ShouldQueue
{
    use Queueable;

    protected PayrollDetail $payrollDetail;

    /**
     * Create a new notification instance.
     */
    public function __construct(PayrollDetail $payrollDetail)
    {
        $this->payrollDetail = $payrollDetail;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payroll = $this->payrollDetail->payroll;
        $salaryMonth = \Carbon\Carbon::parse($payroll->salary_month)->format('F Y');
        $paymentDate = \Carbon\Carbon::parse($payroll->payment_date)->format('d F Y');

        return (new MailMessage)
            ->subject('Slip Gaji '.$salaryMonth.' Telah Dibayar')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Gaji untuk periode **'.$salaryMonth.'** telah dibayarkan.')
            ->line('**Detail Gaji:**')
            ->line('Gaji Pokok: Rp '.number_format($this->payrollDetail->original_salary, 0, ',', '.'))
            ->line('Potongan: Rp '.number_format($this->payrollDetail->original_salary - $this->payrollDetail->final_salary, 0, ',', '.'))
            ->line('Total Diterima: Rp '.number_format($this->payrollDetail->final_salary, 0, ',', '.'))
            ->line('Tanggal Pembayaran: '.$paymentDate)
            ->line('**Kehadiran:**')
            ->line('Hadir: '.$this->payrollDetail->attended_days.' hari')
            ->line('Sakit: '.$this->payrollDetail->sick_days.' hari')
            ->line('Absen: '.$this->payrollDetail->absent_days.' hari')
            ->when($this->payrollDetail->notes, function ($message) {
                return $message->line('Catatan: '.$this->payrollDetail->notes);
            })
            ->action('Lihat Slip Gaji', url('/admin/my-payslips'))
            ->line('Terima kasih atas kontribusi Anda.')
            ->salutation('Salam, Tim Finance');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payroll_detail_id' => $this->payrollDetail->id,
            'payroll_id' => $this->payrollDetail->payroll_id,
            'salary_month' => $this->payrollDetail->payroll->salary_month,
            'payment_date' => $this->payrollDetail->payroll->payment_date,
            'original_salary' => $this->payrollDetail->original_salary,
            'final_salary' => $this->payrollDetail->final_salary,
            'attended_days' => $this->payrollDetail->attended_days,
            'sick_days' => $this->payrollDetail->sick_days,
            'absent_days' => $this->payrollDetail->absent_days,
        ];
    }
}
