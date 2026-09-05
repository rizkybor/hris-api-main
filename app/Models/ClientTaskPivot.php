<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class ClientTaskPivot extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Client');
    }

    /**
     * Nama tabel (custom, bukan konvensi Laravel)
     */
    protected $table = 'client_task_pivots';

    /**
     * Kolom yang boleh diisi (mass assignment)
     */
    protected $fillable = [
        'client_id',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function taskClient()
    {
        return $this->belongsTo(ClientTaskList::class, 'task_client_id');
    }

    public function scopeClient()
    {
        return $this->belongsTo(ClientTaskScope::class, 'scope_client_id');
    }

    public function paymentClient()
    {
        return $this->belongsTo(ClientTaskPayment::class, 'payment_client_id');
    }
}
