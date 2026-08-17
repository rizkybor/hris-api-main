<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class CredentialAccount extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label_password', 'username_email', 'website'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Security');
    }

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

    /**
     * Transparently encrypts on write and decrypts on read (using APP_KEY)
     * so stored third-party credentials are never at rest in plaintext --
     * e.g. in the database file itself, or in a full SQL backup dump.
     */
    protected $casts = [
        'password' => 'encrypted',
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