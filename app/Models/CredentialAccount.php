<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CredentialAccount extends Model
{
    use HasFactory, SoftDeletes;

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

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('label_password', 'like', '%'.$search.'%')
                ->orWhere('username_email', 'like', '%'.$search.'%')
                ->orWhere('website', 'like', '%'.$search.'%')
                ->orWhere('notes', 'like', '%'.$search.'%');
        });
    }
}