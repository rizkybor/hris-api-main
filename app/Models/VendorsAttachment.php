<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorsAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'document_name',
        'document_path',
        'type_file',
        'size_file',
        'description'
    ];

    /**
     * Relasi ke Vendor
     */
    public function vendor()
    {
        return $this->belongsTo(Vendors::class, 'vendor_id');
    }

    /**
     * Scope untuk pencarian
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('document_name', 'like', '%' . $search . '%')
              ->orWhere('type_file', 'like', '%' . $search . '%')
              ->orWhere('size_file', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');
        });
    }
}
