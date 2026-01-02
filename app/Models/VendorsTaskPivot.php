<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorsTaskPivot extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nama tabel (custom, bukan konvensi Laravel)
     */
    protected $table = 'vendors_task_pivots';

    /**
     * Kolom yang boleh diisi (mass assignment)
     */
    protected $fillable = [
        'vendor_id',
        'scope_vendor_id',
        'task_vendor_id',
        'task_payment_id',
        'maintenance',
        'contract_value',
        'contract_status',
        'contract_start',
        'contract_end',
    ];

    /**
     * Casting tipe data
     */
    protected $casts = [
        'maintenance'     => 'boolean',
        'contract_value'  => 'decimal:2',
        'contract_start'  => 'date',
        'contract_end'    => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function vendor()
    {
        return $this->belongsTo(Vendors::class);
    }

    public function scopeVendor()
    {
        return $this->belongsTo(VendorsTaskScope::class, 'scope_vendor_id');
    }

    public function taskVendor()
    {
        return $this->belongsTo(VendorsTaskList::class, 'task_vendor_id');
    }

    public function taskPayment()
    {
        return $this->belongsTo(VendorsTaskPayment::class, 'task_payment_id');
    }
}
