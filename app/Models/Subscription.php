<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subscription extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // `amount` is a computed accessor (sum of `services`), not a real
    // column -- without $appends it silently drops out of toArray()/JSON
    // for any code that serializes a Subscription model directly instead
    // of going through SubscriptionResource (e.g. report endpoints).
    protected $appends = ['amount'];

    protected $fillable = [
        'name',
        'project_id',
        'client_id',
        'billing_cycle',
        'start_date',
        'next_due_date',
        'due_reminder_sent_for',
        'status',
        'last_invoiced_at',
        'notes',
        'created_by',
        'ppn_percentage',
        'admin_fee',
        'bank_name',
        'terms',
        'pph23_type',
        'pph23_percent',
    ];

    protected function casts(): array
    {
        return [
            'ppn_percentage' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'pph23_percent' => 'decimal:2',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'due_reminder_sent_for' => 'date',
            'last_invoiced_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Business Documents');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function services()
    {
        return $this->hasMany(SubscriptionService::class)->orderBy('sort_order');
    }

    /**
     * Total invoice subtotal for this subscription -- the sum of all its
     * services' amounts, replacing the old single `amount` column now that
     * a subscription can bundle several services.
     */
    public function getAmountAttribute(): float
    {
        return (float) $this->services->sum(fn ($service) => (float) $service->amount);
    }

    /**
     * The billing-period label ("September 2026" for monthly, "2026" for
     * yearly) that the *next* invoice generation would cover -- matches
     * the label SubscriptionController::generateInvoice() stores on the
     * Invoice it creates, so it can be used to detect a same-period
     * duplicate before generating another one.
     */
    public function currentPeriodLabel(): ?string
    {
        if (! $this->next_due_date) {
            return null;
        }

        return $this->billing_cycle === 'yearly'
            ? $this->next_due_date->format('Y')
            : $this->next_due_date->format('F Y');
    }

    /**
     * Everything the next invoice for this subscription will bill, computed
     * exactly as SubscriptionController::generateInvoice() stores it -- so
     * the due-date reminder email and the invoice itself can never disagree.
     * Needs `services` (eager-load them when calling this in a loop).
     */
    public function billingBreakdown(): array
    {
        $periodLabel = $this->currentPeriodLabel();

        // One line item per bundled service so several services under the
        // same subscription still land on a single invoice.
        $serviceItems = $this->services->map(fn ($service) => [
            'description' => ($service->product_name ?: $service->service_type)." - {$periodLabel}",
            'quantity' => '1',
            'rate' => null,
            'total' => (float) (string) $service->amount,
        ])->all();

        $subtotal = array_sum(array_column($serviceItems, 'total'));
        $ppnPercentage = (float) (string) $this->ppn_percentage;

        // Optional pass-through registrar cost (e.g. ICANN's fee on a domain
        // service) -- billed as its own line item per service it applies to,
        // but excluded from subtotal/ppn (not part of the VAT taxable base),
        // same treatment as Invoice::admin_fee.
        $icannFeeTotal = (float) $this->services->sum(fn ($service) => (float) ($service->icann_fee ?? 0));
        $icannItems = $this->services
            ->filter(fn ($service) => (float) ($service->icann_fee ?? 0) > 0)
            ->map(fn ($service) => [
                'description' => ($service->product_name ?: $service->service_type)." - ICANN Fee - {$periodLabel}",
                'quantity' => '1',
                'rate' => null,
                'total' => (float) (string) $service->icann_fee,
            ])->values()->all();

        // With several services, ppn_percentage is a blended rate rounded to
        // 2 decimals (see recalculateAggregatePpn()) -- reapplying it to the
        // whole subtotal would drift from what each service's own rate
        // actually adds up to (which the PPN report reads straight off the
        // invoice), so sum each service's exact contribution instead. A
        // single service has only one rate for the whole subtotal, so both
        // give the same result.
        $ppnAmount = $this->services->count() > 1
            ? round($this->services->sum(
                fn ($service) => (float) (string) $service->amount * ((float) ($service->ppn_percentage ?? 0) / 100)
            ))
            : round($subtotal * ($ppnPercentage / 100));
        $adminFee = (float) (string) $this->admin_fee;

        return [
            'period_label' => $periodLabel,
            'service_items' => $serviceItems,
            'icann_items' => $icannItems,
            'subtotal' => $subtotal,
            'ppn_percentage' => $ppnPercentage,
            'ppn_amount' => $ppnAmount,
            'admin_fee' => $adminFee,
            'icann_fee' => $icannFeeTotal,
            'total' => $subtotal + $ppnAmount + $adminFee + $icannFeeTotal,
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }
}
