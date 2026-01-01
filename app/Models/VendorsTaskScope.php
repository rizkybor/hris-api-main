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

    /**
     * Contoh relasi (opsional)
     * Jika nanti dipakai di vendor_tasks
     */
    public function vendorTasks()
    {
        return $this->hasMany(VendorsTaskList::class, 'scope_vendor_id');
    }
}
