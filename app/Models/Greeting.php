<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Greeting extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Greeting');
    }

    protected $fillable = [
        'title',
        'message',
        'greeting_date',
        'is_recurring_yearly',
        'type',
        'audience',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'greeting_date' => 'date',
            'is_recurring_yearly' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    /**
     * Matches entries for the given date: recurring entries by month+day
     * (any year), one-time entries by the exact date.
     */
    public function scopeForDate($query, \Carbon\Carbon $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->where(function ($recurring) use ($date) {
                $recurring->where('is_recurring_yearly', true)
                    ->whereMonth('greeting_date', $date->month)
                    ->whereDay('greeting_date', $date->day);
            })->orWhere(function ($oneTime) use ($date) {
                $oneTime->where('is_recurring_yearly', false)
                    ->whereDate('greeting_date', $date->toDateString());
            });
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
