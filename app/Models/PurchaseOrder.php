<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class PurchaseOrder extends Model
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
        'po_number',
        'type',
        'date',
        'title',
        'client_name',
        'client_address',
        'client_phone',
        'client_wa',
        'items',
        'total',
        'payment_terms',
        'general_terms',
        'warranty_months',
        'replacement_days',
        'buyer_signatory_name',
        'buyer_signatory_title',
        'vendor_signatory_name',
        'vendor_signatory_title',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'items' => 'array',
            'payment_terms' => 'array',
            'total' => 'decimal:2',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('po_number', 'like', '%'.$search.'%')
                ->orWhere('client_name', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%');
        });
    }
}
