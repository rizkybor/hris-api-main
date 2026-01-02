<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorsTaskList extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Kolom yang boleh diisi (mass assignment)
     */
    protected $fillable = [
        'name',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%');
        });
    }
}
