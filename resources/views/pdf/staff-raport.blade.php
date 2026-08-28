<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Same letterhead-background approach as pdf/letter.blade.php --
           see that file for the full reasoning behind the @page margin /
           negative-offset / page-1-clearance trio. Uses the "secondary"
           template per spec, not the template-picker used by Letters.

           Margins here are wider than letter.blade.php's, measured
           directly off template-letter-secondary.png (checked which pixel
           rows aren't blank): the header graphic (logo/name/tagline) ends
           at ~42.2mm from the top, and the footer graphic (address block)
           starts at ~277mm, i.e. ~20mm from the bottom. Letter's narrower
           6mm bottom margin works there only because a letter's own
           content rarely runs long enough to reach the last few mm of a
           page -- this report's tables regularly do, so the margin itself
           has to hold the content back, not just happen not to collide. */
        @page { margin: 48mm 0 24mm 0; }
        .page { position: relative; z-index: 1; margin-top: -25mm; }
        .letterhead { position: fixed; top: -48mm; left: 0; width: 210mm; height: 297mm; z-index: -1; }

        /* A table row that straddles a page break renders its cells'
           borders/background split awkwardly across the gap -- keep each
           row whole, letting the break fall between rows instead. */
        table.data-table tr { page-break-inside: avoid; }

        body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2937; font-size: 11px; }

        .header-row { margin-bottom: 6mm; }

        h1.title { font-size: 16px; margin: 0 0 1mm 0; color: #0c51d9; }
        .subtitle { font-size: 10px; color: #6b7280; margin: 0 0 4mm 0; }

        .employee-name { font-size: 14px; font-weight: bold; margin: 0; }
        .employee-meta { font-size: 10px; color: #4b5563; margin: 1mm 0 0 0; }

        /* "Helvetica"/"Arial" resolve to dompdf's core PDF fonts, which
           don't cover the Unicode star glyph (renders as tofu boxes) --
           DejaVu Sans is dompdf's bundled font with full Unicode coverage,
           so it's forced here specifically for the star characters. */
        .stars { font-family: "DejaVu Sans", sans-serif; font-size: 16px; letter-spacing: 1px; color: #f59e0b; margin: 2mm 0 0 0; }
        .stars .empty { color: #d1d5db; }
        .score-label { font-size: 10px; color: #6b7280; }

        .summary-row { display: table; width: 100%; margin: 4mm 0 6mm 0; }
        .summary-card { display: table-cell; width: 33.33%; padding: 3mm; border: 1px solid #dcdedd; border-radius: 4px; }
        .summary-card .value { font-size: 15px; font-weight: bold; color: #0c51d9; }
        .summary-card .label { font-size: 9px; color: #6b7280; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        table.data-table th, table.data-table td { border: 1px solid #94a3b8; padding: 3px 6px; font-size: 10px; }
        table.data-table th { background-color: #eef2ff; font-weight: bold; text-align: left; }

        h3.section-title { font-size: 12px; margin: 0 0 2mm 0; color: #1f2937; border-bottom: 1px solid #dcdedd; padding-bottom: 1mm; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-letter-secondary.png') }}">

    <div class="page">
        <h1 class="title">Staff Performance Raport</h1>
        <p class="subtitle">
            Period: {{ $period_label }} ({{ \Carbon\Carbon::parse($period['start_date'])->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($period['end_date'])->translatedFormat('d M Y') }})
            &bull; Generated {{ $generated_at->translatedFormat('d M Y H:i') }}
        </p>

        <div class="header-row">
            <p class="employee-name">{{ $employee['name'] }}</p>
            <p class="employee-meta">
                {{ $employee['code'] }}
                @if($employee['job_title']) &bull; {{ $employee['job_title'] }} @endif
                @if($employee['team']) &bull; {{ $employee['team'] }} @endif
            </p>
            <p class="employee-meta">
                {{ ucfirst(str_replace('_', ' ', $employee['employment_type'] ?? '-')) }}
                @if($employee['start_date']) &bull; Joined {{ \Carbon\Carbon::parse($employee['start_date'])->translatedFormat('d M Y') }} @endif
            </p>
            <div class="stars">
                @for($i = 1; $i <= 5; $i++)
                    <span class="{{ $i <= $stars ? '' : 'empty' }}">&#9733;</span>
                @endfor
                <span class="score-label">&nbsp;{{ $overall_score !== null ? $overall_score.'%' : 'No data' }}</span>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="value">{{ $attendance_rate !== null ? $attendance_rate.'%' : '-' }}</div>
                <div class="label">Attendance Rate ({{ $attendance['present'] + $attendance['late'] }}/{{ $attendance['total'] }} days)</div>
            </div>
            <div class="summary-card">
                <div class="value">{{ $task_completion_rate !== null ? $task_completion_rate.'%' : '-' }}</div>
                <div class="label">Task Completion ({{ $tasks['done'] }}/{{ $tasks['total'] }} tasks)</div>
            </div>
            <div class="summary-card">
                <div class="value">{{ $overall_score !== null ? $overall_score.'%' : '-' }}</div>
                <div class="label">Overall Score</div>
            </div>
        </div>

        <h3 class="section-title">Attendance Breakdown</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Present</th>
                    <th>Late</th>
                    <th>Absent</th>
                    <th>Sick Leave</th>
                    <th>Total Records</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $attendance['present'] }}</td>
                    <td>{{ $attendance['late'] }}</td>
                    <td>{{ $attendance['absent'] }}</td>
                    <td>{{ $attendance['sick_leave'] }}</td>
                    <td>{{ $attendance['total'] }}</td>
                </tr>
            </tbody>
        </table>

        <h3 class="section-title">Completed Tasks ({{ count($completed_tasks) }})</h3>
        @if(count($completed_tasks) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 52%;">Task</th>
                        <th style="width: 20%;">Source</th>
                        <th style="width: 20%;">Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completed_tasks as $index => $task)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $task['title'] }}{{ $task['project_name'] ? ' ('.$task['project_name'].')' : '' }}</td>
                            <td>{{ $task['source'] === 'project_task' ? 'Project Task' : 'Staff Task' }}</td>
                            <td>{{ $task['due_date'] ? \Carbon\Carbon::parse($task['due_date'])->translatedFormat('d M Y') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="color: #6b7280; font-size: 10px;">No completed tasks in this period.</p>
        @endif

        @if($performance_review)
            <h3 class="section-title">Latest Performance Review</h3>
            <div style="page-break-inside: avoid; margin-bottom: 6mm;">
                <p style="margin: 0 0 2mm 0; font-size: 10px; color: #6b7280;">
                    Period: {{ $performance_review['period'] ?? '-' }}
                    @if($performance_review['period_start'] && $performance_review['period_end'])
                        ({{ \Carbon\Carbon::parse($performance_review['period_start'])->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($performance_review['period_end'])->translatedFormat('d M Y') }})
                    @endif
                    &bull; Reviewer: {{ $performance_review['reviewer_name'] ?? '-' }}
                    &bull; Status: {{ ucfirst($performance_review['status']) }}
                </p>
                <p style="margin: 0 0 3mm 0; font-size: 11px;">
                    Overall Rating: <strong style="color: #0c51d9;">{{ $performance_review['overall_rating'] }} / 5</strong>
                </p>

                @if(!empty($performance_review['category_scores']))
                    <table class="data-table" style="margin-bottom: 3mm;">
                        <thead>
                            <tr>
                                @foreach($performance_review['category_scores'] as $category => $score)
                                    <th>{{ ucwords(str_replace('_', ' ', $category)) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach($performance_review['category_scores'] as $score)
                                    <td>{{ $score }} / 5</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                @endif

                @if($performance_review['strengths'])
                    <p style="margin: 0 0 2mm 0; font-size: 10px;"><strong>Strengths:</strong> {{ $performance_review['strengths'] }}</p>
                @endif
                @if($performance_review['areas_for_improvement'])
                    <p style="margin: 0 0 2mm 0; font-size: 10px;"><strong>Areas for Improvement:</strong> {{ $performance_review['areas_for_improvement'] }}</p>
                @endif
                @if($performance_review['goals_next_period'])
                    <p style="margin: 0; font-size: 10px;"><strong>Goals for Next Period:</strong> {{ $performance_review['goals_next_period'] }}</p>
                @endif
            </div>
        @endif

        <div style="page-break-inside: avoid; margin-top: 14mm; text-align: right;">
            <p style="margin: 0;">Tangerang Selatan, {{ $generated_at->translatedFormat('d F Y') }}</p>
            <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
            <div style="height: 20mm;"></div>
            <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">Aldi Pratama Putra, S.Ikom</p>
            <p style="margin: 0;">Director</p>
        </div>
    </div>
</body>
</html>
