<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One UAT (User Acceptance Test) round of a project. The number of rounds
 * agreed with the client is Project::$uat_rounds_planned; extra rounds can
 * still be recorded (they show as outside the agreement).
 */
class ProjectUatSession extends Model
{
    use LogsActivity;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PASSED = 'passed';

    public const STATUS_REVISION = 'revision';

    public const STATUSES = [self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS, self::STATUS_PASSED, self::STATUS_REVISION];

    /** A round that took place (counts towards the agreed rounds). */
    public const DONE_STATUSES = [self::STATUS_PASSED, self::STATUS_REVISION];

    public const MAX_ROUNDS = 20;

    protected $fillable = [
        'project_id',
        'round',
        'date',
        'status',
        'notes',
        'findings',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'attachment_size',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'date' => 'date:Y-m-d',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['round', 'date', 'status', 'notes', 'findings', 'attachment_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Project');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return list<string> */
    public function findingList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->findings))));
    }
}
