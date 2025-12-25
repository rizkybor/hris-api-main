<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CredentialAccount extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal.
     */
    protected $fillable = [
        'label_password',
        'username_email',
        'password',
        'website',
        'notes',
    ];

    /**
     * Jika kamu ingin menyembunyikan password saat model diubah ke Array/JSON.
     */
    protected $hidden = [
        'password',
    ];
}