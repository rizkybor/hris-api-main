<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FilesCompany extends Model
{
    use HasFactory;

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
}