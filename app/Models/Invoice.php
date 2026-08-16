<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Invoice extends Model
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
        'invoice_number',
        'client_code',
        'client_name',
        'client_pic',
        'client_email',
        'client_phone',
        'date',
        'items',
        'subtotal',
        'ppn_percentage',
        'ppn_amount',
        'admin_fee',
        'total',
        'bank_name',
        'bank_account',
        'terms',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'items' => 'array',
            'subtotal' => 'decimal:2',
            'ppn_percentage' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipts()
    {
        return $this->hasMany(PaymentReceipt::class);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%');
        });
    }
}
