<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
            ->logOnly(['round', 'date', 'status', 'notes', 'attachment_name'])
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

    /** Places the round was held at (same time, different locations). */
    public function locations()
    {
        return $this->hasMany(ProjectUatLocation::class)->orderBy('position')->orderBy('id');
    }

    /** Findings and notes, in the order they were entered. */
    public function items()
    {
        return $this->hasMany(ProjectUatItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return Collection<int, ProjectUatItem> */
    public function findingItems()
    {
        return $this->items->where('type', ProjectUatItem::TYPE_FINDING)->values();
    }
}
