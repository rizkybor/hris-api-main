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

    protected $fillable = [
        'name',
        'service_type',
        'product_name',
        'project_id',
        'client_id',
        'billing_cycle',
        'amount',
        'start_date',
        'next_due_date',
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
            'amount' => 'decimal:2',
            'ppn_percentage' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'pph23_percent' => 'decimal:2',
            'start_date' => 'date',
            'next_due_date' => 'date',
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

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }
}
