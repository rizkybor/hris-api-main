<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PersonName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'profile_photo', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('User Management');
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The one account that must always exist and can never be deleted,
     * regardless of who's asking.
     */
    public const PROTECTED_EMAIL = 'superadmin@jcdigital.co.id';

    public function isProtected(): bool
    {
        return $this->email === self::PROTECTED_EMAIL;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_photo',
        'name',
        'full_name',
        'show_degree',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'show_degree' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PersonName::flush());
    }

    /**
     * Shown name: the stored full name ("Aldi PP, S.Ikom") with or without
     * titles/degrees, as the person chose in My Profile (show_degree,
     * default off). Applies everywhere a user's name is read or serialized.
     * Writing `name` without titles/degrees keeps the existing ones (forms
     * that showed the display name don't strip them); use `full_name` to
     * set the name exactly as typed.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $show = array_key_exists('show_degree', $attributes)
                    ? (bool) $attributes['show_degree']
                    : (isset($attributes['id']) && PersonName::showsDegree((int) $attributes['id']));

                return PersonName::display($value, $show);
            },
            set: fn ($value) => $value === null ? null : PersonName::mergeKeepingDegree((string) $value, $this->attributes['name'] ?? null),
        );
    }

    /** The name exactly as stored, titles and degrees included. */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['name'] ?? null,
            set: fn ($value) => ['name' => $value === null ? null : trim(preg_replace('/\s+/', ' ', (string) $value))],
        );
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function leadTeams()
    {
        return $this->hasMany(Team::class, 'team_lead_id');
    }

    public function pinnedMeetingNotes()
    {
        return $this->belongsToMany(MeetingNote::class, 'user_pinned_notes', 'user_id', 'document_id')->withTimestamps();
    }
}
