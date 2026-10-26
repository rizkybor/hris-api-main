<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class PaymentReceipt extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Business Documents');
    }

    protected $fillable = [
        'receipt_number',
        'client_code',
        'date',
        'received_from',
        'amount',
        'pph23_type',
        'pph23_percent',
        'pph23_amount',
        'for_payment_of',
        'invoice_id',
        'payment_status',
        'recipient_name',
        'use_signature',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'pph23_percent' => 'decimal:2',
            'pph23_amount' => 'decimal:2',
            'use_signature' => 'boolean',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('receipt_number', 'like', '%'.$search.'%')
                ->orWhere('received_from', 'like', '%'.$search.'%');
        });
    }

    /**
     * "For Payment of" as printed, and whether the PDF should still add
     * "berdasarkan Invoice No. ..." after it. That reference is added for a
     * linked invoice, so the same reference typed (or auto-filled) at the
     * end of the field is dropped to avoid printing it twice; text that
     * already names the invoice mid-sentence is printed as written.
     *
     * @return array{text: string, append_invoice: bool}
     */
    public function forPaymentLine(): array
    {
        $text = trim((string) $this->for_payment_of);
        $number = $this->invoice?->invoice_number;

        if (! $number) {
            return ['text' => $text, 'append_invoice' => false];
        }

        $pattern = '/[\s,.;-]*(berdasarkan\s+|sesuai\s+|for\s+)?(invoice|inv)\.?\s*(no\.?|nomor|number|#)?\s*:?\s*'.preg_quote($number, '/').'[\s.]*$/iu';
        $text = trim(preg_replace($pattern, '', $text), " \t\n\r,.;-");

        if (mb_stripos($text, $number) !== false) {
            return ['text' => $text, 'append_invoice' => false];
        }

        return ['text' => $text !== '' ? $text : 'Pembayaran', 'append_invoice' => true];
    }
}
