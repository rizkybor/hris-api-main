<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FilesCompany extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Jika nama tabel di migrasi kamu adalah 'files_companies', 
     * Laravel akan mengenalinya secara otomatis. 
     * Namun jika kamu menamainya 'files_company' (tunggal), aktifkan baris di bawah ini:
     */
    // protected $table = 'files_companies';

    protected $fillable = [
        'path',
        'name',
        'description',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('path', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
        });
    }
}