<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Vendors extends Model
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

    protected $fillable = [
        'name',
        'pic_name',
        'pic_phone',
        'email',
        'address',
        'type',
        'field',
        'notes'
    ];

     /**
     * Relasi ke task pivots
     */
    public function taskPivots()
    {
        return $this->hasMany(VendorsTaskPivot::class, 'vendor_id');
    }

    /**
     * Relasi ke attachments
     */
    public function attachments()
{
    return $this->hasMany(VendorsAttachment::class, 'vendor_id');
}


    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
                ->orWhere('pic_name', 'like', '%' . $search . '%')
                ->orWhere('pic_phone', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('address', 'like', '%' . $search . '%')
                ->orWhere('type', 'like', '%' . $search . '%')
                ->orWhere('field', 'like', '%' . $search . '%')
            ;
        });
    }
}
