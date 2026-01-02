<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorsTaskScope extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Kolom yang boleh diisi (mass assignment)
     */
    protected $fillable = [
        'name',
    ];

    public function scopeVendor()
    {
        return $this->hasMany(VendorsTaskPivot::class, 'scope_vendor_id');
    }
}
