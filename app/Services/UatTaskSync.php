<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUatItem;
use Illuminate\Support\Str;

/**
 * Keeps one board task per UAT finding/note. The board only reads two
 * stages from UAT: To Do while the item is open, Done once it is checked
 * off as revised. Name, description and type follow the item.
 */
class UatTaskSync
{
    public const TYPE_LABELS = [
        ProjectUatItem::TYPE_FINDING => 'Temuan UAT',
        ProjectUatItem::TYPE_NOTE => 'Catatan UAT',
    ];

    public const TYPE_COLORS = [
        ProjectUatItem::TYPE_FINDING => 'rose',
        ProjectUatItem::TYPE_NOTE => 'amber',
    ];

    public const PRIORITIES = [
        ProjectUatItem::TYPE_FINDING => 'high',
        ProjectUatItem::TYPE_NOTE => 'medium',
    ];

    /** The only statuses a UAT task can have. */
    public const STATUSES = [TaskStatus::TODO->value, TaskStatus::DONE->value];

    public function syncItem(ProjectUatItem $item): ProjectTask
    {
        $item->loadMissing(['session', 'location']);
        $session = $item->session;
        $status = $item->is_resolved ? TaskStatus::DONE->value : TaskStatus::TODO->value;

        $values = [
            'project_id' => $session->project_id,
            'name' => $this->name($item),
            'description' => $this->description($item),
            'type' => self::TYPE_LABELS[$item->type] ?? 'UAT',
            'color' => self::TYPE_COLORS[$item->type] ?? 'slate',
            'status' => $status,
        ];

        $task = ProjectTask::withTrashed()->where('project_uat_item_id', $item->id)->first();
        if (! $task) {
            return ProjectTask::create($values + [
                'project_uat_item_id' => $item->id,
                'priority' => self::PRIORITIES[$item->type] ?? 'medium',
                'position' => $this->endOf($session->project_id, $status),
            ]);
        }

        if ($task->trashed()) {
            $task->restore();
        }
        if ($task->status !== $status) {
            $values['position'] = $this->endOf($session->project_id, $status);
        }
        $task->fill($values);
        if ($task->isDirty()) {
            $task->save();
        }

        return $task;
    }

    /** All items of a project (e.g. after its rounds were renumbered). */
    public function syncProject(Project|int $project): void
    {
        $projectId = $project instanceof Project ? $project->id : $project;
        ProjectUatItem::query()
            ->whereHas('session', fn ($query) => $query->where('project_id', $projectId))
            ->with(['session', 'location'])
            ->orderBy('id')
            ->each(fn (ProjectUatItem $item) => $this->syncItem($item));
    }

    public function syncAll(): void
    {
        ProjectUatItem::query()->with(['session', 'location'])->orderBy('id')
            ->each(fn (ProjectUatItem $item) => $this->syncItem($item));
    }

    /** "[UAT 1] Fitur › Menu — description", one line, fits the column. */
    private function name(ProjectUatItem $item): string
    {
        $where = collect([$item->feature, $item->menu])->filter()->implode(' › ');
        $text = Str::squish($item->description);

        return Str::limit('[UAT '.$item->session->round.'] '.($where !== '' ? $where.' — ' : '').$text, 250);
    }

    private function description(ProjectUatItem $item): string
    {
        $location = $item->location;
        $sessionLabel = $location
            ? 'Sesi '.($location->position + 1).($location->location ? ' ('.$location->location.')' : '')
            : null;
        $lines = array_filter([
            'UAT '.$item->session->round.($sessionLabel ? ' · '.$sessionLabel : ''),
            $item->feature ? 'Fitur: '.$item->feature : null,
            $item->menu ? 'Menu: '.$item->menu : null,
        ]);

        return implode("\n", $lines)."\n\n".$item->description;
    }

    private function endOf(int $projectId, string $status): float
    {
        $max = ProjectTask::where('project_id', $projectId)->where('status', $status)->max('position');

        return $max === null ? 1000 : $max + 1000;
    }
}
