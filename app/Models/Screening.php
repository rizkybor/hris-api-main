<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** One client's needs questionnaire and the services recommended from it. */
class Screening extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = ['draft', 'invited', 'new', 'in_review', 'reviewed', 'won', 'lost'];

    public const SOURCES = ['landing', 'invite', 'internal'];

    protected $fillable = [
        'code', 'token', 'token_expires_at', 'source', 'status', 'lang', 'contact_name', 'company', 'email', 'phone',
        'client_id', 'screening_template_id', 'template_snapshot', 'answers', 'result', 'internal_notes',
        'submitted_at', 'ip_address', 'created_by',
    ];

    protected $hidden = ['ip_address'];

    protected function casts(): array
    {
        return [
            'template_snapshot' => 'array',
            'answers' => 'array',
            'result' => 'array',
            'token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Screening $s) {
            $s->token ??= Str::random(48);
            $s->code ??= static::nextCode();
        });
    }

    /** SCR-YYMM-0001, numbered per month. */
    public static function nextCode(): string
    {
        $prefix = 'SCR-'.now('Asia/Jakarta')->format('ym').'-';
        $last = static::withTrashed()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');

        return $prefix.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function items()
    {
        return $this->hasMany(ScreeningItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Can the client still open and save through the link? */
    public function linkIsOpen(): bool
    {
        return ! $this->trashed()
            && in_array($this->status, ['draft', 'invited'], true)
            && (! $this->token_expires_at || $this->token_expires_at->isFuture());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'contact_name', 'company', 'email', 'phone', 'client_id', 'internal_notes'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('Client Screening');
    }
}
