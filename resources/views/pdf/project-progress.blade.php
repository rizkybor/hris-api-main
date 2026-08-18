<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 11px; }
        .top-bar { position: fixed; top: 0; left: 0; right: 0; height: 10mm; background-color: #0b1d51; }
        .bottom-bar { position: fixed; bottom: 0; left: 0; right: 0; height: 5mm; background-color: #0b1d51; }
        .watermark { position: fixed; top: 110mm; left: 50mm; width: 110mm; height: 93mm; opacity: 0.06; }
        .page { padding: 18mm 15mm 18mm 15mm; }
        .header-row { width: 100%; }
        .header-row td { border: none; vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; color: #0b1d51; margin: 0; }
        .doc-title { font-size: 24px; font-weight: bold; color: #0b1d51; text-align: right; margin: 0; }
        .generated-at { text-align: right; color: #64748b; font-size: 9.5px; margin: 2px 0 0 0; }
        .label { color: #0b1d51; font-weight: bold; }
        .section-title { color: #0b1d51; font-size: 13px; font-weight: bold; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 0.3px; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 2px 0; font-size: 10.5px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9.5px; font-weight: bold; }
        .badge-status-active, .badge-status-on-track { background-color: #dcfce7; color: #15803d; }
        .badge-status-planning, .badge-status-draft { background-color: #e0e7ff; color: #4338ca; }
        .badge-status-on_hold, .badge-status-behind { background-color: #fef9c3; color: #a16207; }
        .badge-status-completed { background-color: #dbeafe; color: #1d4ed8; }
        .badge-status-cancelled, .badge-status-overdue { background-color: #fee2e2; color: #b91c1c; }
        .badge-priority-low { background-color: #f1f5f9; color: #475569; }
        .badge-priority-medium { background-color: #fef9c3; color: #a16207; }
        .badge-priority-high, .badge-priority-urgent { background-color: #fee2e2; color: #b91c1c; }

        .stat-table { margin-top: 6mm; }
        .stat-box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; text-align: center; }
        .stat-value { font-size: 20px; font-weight: bold; color: #0b1d51; margin: 0; }
        .stat-label { font-size: 9px; color: #64748b; margin: 2px 0 0 0; text-transform: uppercase; letter-spacing: 0.2px; }

        .progress-track { width: 100%; height: 8px; background-color: #e2e8f0; border-radius: 4px; }
        .progress-fill { height: 8px; background-color: #0b1d51; border-radius: 4px; }

        .task-table { margin-top: 3mm; }
        .task-table th { border-bottom: 1.5px solid #0b1d51; color: #0b1d51; text-align: left; padding: 5px 4px; font-size: 10px; }
        .task-table td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; font-size: 10px; vertical-align: top; }
        .empty-note { color: #94a3b8; font-size: 10px; padding: 6px 4px; font-style: italic; }

        .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 4px; }
        .dot-done { background-color: #16a34a; }
        .dot-ongoing { background-color: #2563eb; }
        .dot-pending { background-color: #ca8a04; }
        .dot-cancelled { background-color: #dc2626; }

        .footer { position: fixed; bottom: 7mm; left: 15mm; right: 15mm; font-size: 9.5px; color: #0b1d51; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="bottom-bar"></div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">

    <div class="page">
        <table class="header-row">
            <tr>
                <td style="width: 50%;"><p class="brand">JENDELA CAKRA<br>DIGITAL</p></td>
                <td style="width: 50%;">
                    <p class="doc-title">PROJECT PROGRESS REPORT</p>
                    <p class="generated-at">Generated on {{ strtoupper($generatedAt->translatedFormat('d F Y, H:i')) }}</p>
                </td>
            </tr>
        </table>

        {{-- Project Info --}}
        <table style="margin-top: 8mm;">
            <tr>
                <td style="width: 60%; border: none; vertical-align: top;">
                    <p class="label" style="font-size: 15px; margin: 0 0 3px 0;">{{ $project->name }}</p>
                    <p style="margin: 0 0 4px 0;">
                        <span class="badge badge-status-{{ $project->status }}">{{ str_replace('_', ' ', strtoupper($project->status)) }}</span>
                        <span class="badge badge-priority-{{ $project->priority }}">{{ strtoupper($project->priority) }} PRIORITY</span>
                    </p>
                    <table class="info-table">
                        <tr><td style="width: 30%;">Project Leader</td><td>{{ $project->projectLeader?->user?->name ?? '-' }}</td></tr>
                        <tr><td>Teams Assigned</td><td>{{ $project->teams->pluck('name')->implode(', ') ?: '-' }}</td></tr>
                        <tr><td>Type</td><td>{{ $project->type ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 40%; border: none; vertical-align: top;">
                    <table class="info-table">
                        <tr><td style="width: 45%;" class="label">Start Date</td><td>{{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->translatedFormat('d F Y') : '-' }}</td></tr>
                        <tr><td class="label">End Date</td><td>{{ $project->end_date ? \Carbon\Carbon::parse($project->end_date)->translatedFormat('d F Y') : 'Ongoing' }}</td></tr>
                        <tr><td class="label">Duration</td><td>{{ $timeline['totalDurationDays'] !== null ? $timeline['totalDurationDays'].' days' : '-' }}</td></tr>
                        <tr><td class="label">Running For</td><td>{{ $timeline['daysElapsed'] !== null ? $timeline['daysElapsed'].' days' : '-' }}</td></tr>
                        <tr>
                            <td class="label">Schedule Status</td>
                            <td><span class="badge badge-status-{{ strtolower(str_replace(' ', '-', $timeline['status'])) }}">{{ strtoupper($timeline['status']) }}</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Progress Overview --}}
        <div style="margin-top: 8mm;">
            <p class="section-title">Progress Overview</p>
            <table style="margin-top: 2mm;">
                <tr>
                    <td style="width: 70%; border: none; vertical-align: middle;">
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ $progress }}%;"></div>
                        </div>
                    </td>
                    <td style="width: 30%; border: none; text-align: right; vertical-align: middle;">
                        <span style="font-size: 18px; font-weight: bold; color: #0b1d51;">{{ $progress }}% Complete</span>
                    </td>
                </tr>
            </table>

            <table class="stat-table">
                <tr>
                    <td style="width: 20%; padding-right: 4px;">
                        <div class="stat-box">
                            <p class="stat-value">{{ $totalTasks }}</p>
                            <p class="stat-label">Total Tasks</p>
                        </div>
                    </td>
                    <td style="width: 20%; padding-right: 4px;">
                        <div class="stat-box">
                            <p class="stat-value" style="color: #16a34a;">{{ $doneTasks->count() }}</p>
                            <p class="stat-label">Done</p>
                        </div>
                    </td>
                    <td style="width: 20%; padding-right: 4px;">
                        <div class="stat-box">
                            <p class="stat-value" style="color: #2563eb;">{{ $ongoingTasks->count() }}</p>
                            <p class="stat-label">In Progress</p>
                        </div>
                    </td>
                    <td style="width: 20%; padding-right: 4px;">
                        <div class="stat-box">
                            <p class="stat-value" style="color: #ca8a04;">{{ $pendingTasks->count() }}</p>
                            <p class="stat-label">Pending</p>
                        </div>
                    </td>
                    <td style="width: 20%;">
                        <div class="stat-box">
                            <p class="stat-value" style="color: #dc2626;">{{ $cancelledTasks->count() }}</p>
                            <p class="stat-label">Cancelled</p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Completed Tasks --}}
        <div style="margin-top: 7mm;">
            <p class="section-title"><span class="dot dot-done"></span>Completed ({{ $doneTasks->count() }})</p>
            <table class="task-table">
                <thead>
                    <tr>
                        <th style="width: 55%;">Task</th>
                        <th style="width: 25%;">Assignee</th>
                        <th style="width: 20%;">Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($doneTasks as $task)
                        <tr>
                            <td>{{ $task->name }}</td>
                            <td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->translatedFormat('d M Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-note">No completed tasks yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Ongoing Tasks --}}
        <div style="margin-top: 6mm;">
            <p class="section-title"><span class="dot dot-ongoing"></span>In Progress / Review ({{ $ongoingTasks->count() }})</p>
            <table class="task-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Task</th>
                        <th style="width: 20%;">Assignee</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 20%;">Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ongoingTasks as $task)
                        <tr>
                            <td>{{ $task->name }}</td>
                            <td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $task->status === 'in_progress' ? 'In Progress' : 'Review' }}</td>
                            <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->translatedFormat('d M Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-note">No tasks currently in progress.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pending Tasks --}}
        <div style="margin-top: 6mm;">
            <p class="section-title"><span class="dot dot-pending"></span>Pending / Not Started ({{ $pendingTasks->count() }})</p>
            <table class="task-table">
                <thead>
                    <tr>
                        <th style="width: 55%;">Task</th>
                        <th style="width: 25%;">Assignee</th>
                        <th style="width: 20%;">Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingTasks as $task)
                        <tr>
                            <td>{{ $task->name }}</td>
                            <td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->translatedFormat('d M Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-note">No pending tasks.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cancelledTasks->count() > 0)
            <div style="margin-top: 6mm;">
                <p class="section-title"><span class="dot dot-cancelled"></span>Cancelled ({{ $cancelledTasks->count() }})</p>
                <table class="task-table">
                    <thead>
                        <tr>
                            <th style="width: 55%;">Task</th>
                            <th style="width: 25%;">Assignee</th>
                            <th style="width: 20%;">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cancelledTasks as $task)
                            <tr>
                                <td>{{ $task->name }}</td>
                                <td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td>
                                <td>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->translatedFormat('d M Y') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="footer">
        <table>
            <tr>
                <td style="border: none; width: 33%;">contact@jcdigital.co.id</td>
                <td style="border: none; width: 34%; text-align: center;">www.jcdigital.co.id</td>
                <td style="border: none; width: 33%; text-align: right;">Phone +62 878-8279-2511</td>
            </tr>
        </table>
    </div>
</body>
</html>
