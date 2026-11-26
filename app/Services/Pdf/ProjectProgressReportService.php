<?php

namespace App\Services\Pdf;

use App\Models\Project;
use App\Models\ProjectUatSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Carbon\Carbon;

/**
 * The project progress report PDF: tasks by status, the project timeline
 * (development, retention, total) and the UAT rounds.
 */
class ProjectProgressReportService
{
    public function load(Project|int|string $project): Project
    {
        $id = $project instanceof Project ? $project->id : $project;

        return Project::with([
            'projectLeader.user',
            'projectLeader.jobInformation',
            'teams',
            'client',
            'tasks.assignee.user',
            'tasks.assignees.user',
            'uatSessions' => fn ($query) => $query->orderBy('round'),
        ])->findOrFail($id);
    }

    /** Numbers shared by the report and the cover letter text. */
    public function summary(Project $project): array
    {
        $tasks = $project->tasks;
        $total = $tasks->count();
        $done = $tasks->where('status', 'done')->count();

        return [
            'totalTasks' => $total,
            'doneCount' => $done,
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    public function make(Project $project): DomPdf
    {
        $tasks = $project->tasks;
        $summary = $this->summary($project);

        return Pdf::loadView('pdf.project-progress', [
            'project' => $project,
            'totalTasks' => $summary['totalTasks'],
            'doneTasks' => $tasks->where('status', 'done')->values(),
            'ongoingTasks' => $tasks->whereIn('status', ['in_progress', 'review'])->values(),
            'pendingTasks' => $tasks->where('status', 'todo')->values(),
            'cancelledTasks' => $tasks->where('status', 'cancelled')->values(),
            'progress' => $summary['progress'],
            'timeline' => $this->timeline($project, $summary['progress']),
            'phases' => $this->phases($project),
            'uat' => $this->uat($project),
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4');
    }

    public static function filename(Project $project): string
    {
        return str_replace([' ', '/'], '-', $project->name).'-progress-report.pdf';
    }

    /**
     * Development / Retention / Total, each with its dates, length and
     * state (Upcoming, Running, Done).
     */
    public function phases(Project $project): array
    {
        $today = Carbon::today();
        $completed = $project->status === 'completed';
        $phase = function (?string $label, $start, $end) use ($today, $completed) {
            $start = $start ? Carbon::parse($start)->startOfDay() : null;
            $end = $end ? Carbon::parse($end)->startOfDay() : null;
            $state = match (true) {
                ! $start || ! $end => null,
                $completed || $today->gt($end) => 'Done',
                $today->lt($start) => 'Upcoming',
                default => 'Running',
            };

            return [
                'label' => $label,
                'start' => $start,
                'end' => $end,
                'length' => $start && $end ? self::length($start, $end) : null,
                'state' => $state,
            ];
        };

        $totalEnd = $project->retention_end_date ?? $project->end_date;

        return [
            'development' => $phase('Development', $project->start_date, $project->end_date),
            'retention' => $phase('Retention', $project->retention_start_date, $project->retention_end_date),
            'total' => $phase('Total', $project->start_date, $totalEnd),
        ];
    }

    /** "3 months 2 days" (30-day months, like the app's duration labels). */
    public static function length(Carbon $start, Carbon $end): string
    {
        $days = max(1, (int) $start->diffInDays($end));
        $months = intdiv($days, 30);
        $rest = $days % 30;
        $parts = [];
        if ($months) {
            $parts[] = $months.' '.($months === 1 ? 'month' : 'months');
        }
        if ($rest || ! $months) {
            $parts[] = $rest.' '.($rest === 1 ? 'day' : 'days');
        }

        return implode(' ', $parts);
    }

    public function uat(Project $project): array
    {
        $sessions = $project->uatSessions;
        $passed = $sessions->firstWhere('status', ProjectUatSession::STATUS_PASSED);

        return [
            'planned' => $project->uat_rounds_planned,
            'done' => $sessions->whereIn('status', ProjectUatSession::DONE_STATUSES)->count(),
            'passedRound' => $passed?->round,
            'sessions' => $sessions,
        ];
    }

    /**
     * Elapsed/expected-vs-actual pace, mirroring the frontend's
     * getProjectHealth() so the PDF and the in-app banner agree (the
     * retention phase included).
     */
    public function timeline(Project $project, int $actualProgress): array
    {
        if (! $project->start_date || ! $project->end_date) {
            return [
                'daysElapsed' => null,
                'totalDurationDays' => null,
                'daysRemaining' => null,
                'expectedProgress' => null,
                'status' => 'No timeline set',
            ];
        }

        $start = Carbon::parse($project->start_date)->startOfDay();
        $end = Carbon::parse($project->end_date)->startOfDay();
        $today = Carbon::now()->startOfDay();

        $totalDurationDays = max(1, (int) $start->diffInDays($end));
        $daysElapsed = (int) min($totalDurationDays, max(0, $start->diffInDays($today, false)));
        $daysRemaining = (int) $today->diffInDays($end, false);
        $expectedProgress = (int) round(($daysElapsed / $totalDurationDays) * 100);

        $inRetention = $project->retention_start_date && $project->retention_end_date
            && $today->gte($project->retention_start_date->copy()->startOfDay());

        if (in_array($project->status, ['completed', 'cancelled'])) {
            $status = $project->status === 'completed' ? 'Completed' : 'Cancelled';
        } elseif ($inRetention) {
            $status = $today->gt($project->retention_end_date) ? 'Retention Ended' : 'In Retention';
        } elseif ($daysRemaining < 0 && $actualProgress < 100) {
            $status = 'Overdue';
        } elseif (($expectedProgress - $actualProgress) >= 10) {
            $status = 'Behind Schedule';
        } else {
            $status = 'On Track';
        }

        return [
            'daysElapsed' => $daysElapsed,
            'totalDurationDays' => $totalDurationDays,
            'daysRemaining' => $daysRemaining,
            'expectedProgress' => $expectedProgress,
            'status' => $status,
        ];
    }
}
