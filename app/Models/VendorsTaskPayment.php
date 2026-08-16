<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class VendorsTaskPayment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Vendor');
    }

    /**
     * Kolom yang boleh diisi (mass assignment)
     */
    protected $fillable = [
        'document_name',
        'document_path',
        'amount',
        'payment_date'
    ];

    /**
     * Casting tipe data
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_name', 'like', '%' . $search . '%')
                ->orWhere('payment_date', 'like', '%' . $search . '%');
        });
    }

    public function paymentVendor()
    {
        return $this->hasMany(VendorsTaskPivot::class, 'payment_vendor_id');
    }
}
