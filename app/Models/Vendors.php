<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendors extends Model
{
    use HasFactory, SoftDeletes;

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
     * Relasi ke vendors_task_pivots
     */
    public function vendorTasks()
    {
        return $this->hasMany(VendorsTaskPivot::class, 'vendor_id');
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
