<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Announcement extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Announcement');
    }

    protected $fillable = [
        'title',
        'body',
        'audience',
        'is_pinned',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'expires_at' => 'date',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
        });
    }

    public function scopeForAudience($query, ?string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->where('audience', 'all');
            if ($role) {
                $q->orWhere('audience', $role);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
