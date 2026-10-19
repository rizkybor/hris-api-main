<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A server reporting its disk / memory / CPU usage through the HRIS server
 * agent (see ServerAgentController), shown under Insights > Server Storage.
 */
class MonitoredServer extends Model
{
    use SoftDeletes, LogsActivity;

    // A server that hasn't reported for this long is shown as offline (the
    // agent reports every 5 minutes).
    public const OFFLINE_AFTER_MINUTES = 15;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'ip_address', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Server Storage');
    }

    protected $fillable = [
        'name',
        'ip_address',
        'description',
        'token_hash',
        'token_prefix',
        'last_report',
        'last_report_at',
        'last_report_ip',
        'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_report' => 'array',
            'last_report_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 'online' | 'offline' | 'pending' (never reported yet). */
    public function status(): string
    {
        if (! $this->last_report_at) {
            return 'pending';
        }

        return $this->last_report_at->gt(now()->subMinutes(self::OFFLINE_AFTER_MINUTES)) ? 'online' : 'offline';
    }

    /**
     * Issues a fresh agent token (replacing any previous one) and returns
     * the plain value -- the only time it's ever available.
     */
    public function issueToken(): string
    {
        $token = 'hsa_'.Str::random(44);
        $this->token_hash = hash('sha256', $token);
        $this->token_prefix = substr($token, 0, 10);

        return $token;
    }

    public static function findByToken(?string $token): ?self
    {
        return $token ? static::where('token_hash', hash('sha256', $token))->first() : null;
    }
}
