<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'background_path',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function getBackgroundUrlAttribute(): ?string
    {
        return $this->background_path ? asset('storage/'.$this->background_path) : null;
    }
}
