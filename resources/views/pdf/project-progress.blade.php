<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.report-style')
</head>
<body>
{{-- Keep to the multi-line PHP block form in this view: Blade mis-parses a
     file that mixes it with the one-line form. --}}
@php
    $fmt = fn ($date, $pattern = 'd M Y') => $date ? \Carbon\Carbon::parse($date)->locale('id')->translatedFormat($pattern) : '—';
    $statusLabel = ['draft' => 'Draf', 'planning' => 'Perencanaan', 'active' => 'Aktif', 'on_hold' => 'Ditunda', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    $priorityLabel = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'];
    $typeLabel = ['web_development' => 'Pengembangan Web', 'mobile_app' => 'Aplikasi Mobile', 'design' => 'Desain', 'marketing' => 'Pemasaran', 'research' => 'Riset', 'infrastructure' => 'Infrastruktur'];
    $scheduleLabel = [
        'Completed' => ['Selesai', 'ok'], 'Cancelled' => ['Dibatalkan', 'muted'], 'In Retention' => ['Masa retensi', ''],
        'Retention Ended' => ['Retensi selesai', 'ok'], 'Overdue' => ['Lewat tenggat', 'bad'], 'Behind Schedule' => ['Tertinggal dari jadwal', 'warn'],
        'On Track' => ['Sesuai jadwal', 'ok'], 'No timeline set' => ['Jadwal belum diatur', 'muted'],
    ];
    $phaseState = ['Done' => 'Selesai', 'Running' => 'Berjalan', 'Upcoming' => 'Belum mulai'];
    $uatResult = ['scheduled' => 'Terjadwal', 'in_progress' => 'Berjalan', 'passed' => 'Lulus', 'revision' => 'Perlu revisi'];
    $uatTone = ['passed' => 'ok', 'revision' => 'warn'];
    $assignee = fn ($task) => $task->assignees->pluck('user.name')->filter()->join(', ') ?: ($task->assignee?->user?->name ?? '—');
    [$scheduleText, $scheduleTone] = $scheduleLabel[$timeline['status']] ?? [$timeline['status'], ''];
    $phaseRows = ['development' => 'Development', 'retention' => 'Masa retensi'];
    $groups = [
        ['Sedang dikerjakan / ditinjau', $ongoingTasks],
        ['Belum dimulai', $pendingTasks],
        ['Selesai', $doneTasks],
        ['Dibatalkan', $cancelledTasks],
    ];
    $showExpected = $timeline['expectedProgress'] !== null && ! in_array($timeline['status'], ['Completed', 'Cancelled', 'In Retention', 'Retention Ended'], true);
    $section = 0;
    $no = 0;
@endphp

    @include('pdf.partials.report-masthead', ['docType' => 'Laporan Progres Proyek', 'docDate' => $generatedAt])

    <p class="doc-title">{{ $project->name }}</p>
    <p class="doc-subtitle">{{ $project->client?->name ?? 'Tanpa klien' }}</p>
    <p class="doc-meta">
        Status <strong>{{ $statusLabel[$project->status] ?? $project->status }}</strong>
        &nbsp;&middot;&nbsp; Prioritas <strong>{{ $priorityLabel[$project->priority] ?? ($project->priority ?? '—') }}</strong>
        &nbsp;&middot;&nbsp; Jadwal <strong class="{{ $scheduleTone }}">{{ $scheduleText }}</strong>
        @if($project->status === 'completed' && $project->completed_at)
            &nbsp;&middot;&nbsp; Diselesaikan <strong>{{ $fmt($project->completed_at->copy()->tz('Asia/Jakarta'), 'd F Y') }}</strong>
        @endif
    </p>

    {{-- Ringkasan --}}
    <h2><span class="n">{{ ++$section }}.</span>Ringkasan</h2>
    <table class="figures">
        <tr>
            <td style="width: 25%;">
                <p class="figure">{{ $progress }}%</p>
                <p class="figure-label">Progres pekerjaan</p>
            </td>
            <td style="width: 25%;">
                <p class="figure">{{ $doneTasks->count() }}<span class="muted" style="font-size: 11pt; font-weight: normal;"> / {{ $totalTasks }}</span></p>
                <p class="figure-label">Pekerjaan selesai</p>
            </td>
            <td style="width: 25%;">
                <p class="figure" style="font-size: 13pt;">{{ $phases['total']['length'] ?? '—' }}</p>
                <p class="figure-label">Total waktu proyek</p>
            </td>
            <td style="width: 25%;">
                <p class="figure">{{ $uat['planned'] ? $uat['done'].' / '.$uat['planned'] : ($uat['done'] ?: '—') }}</p>
                <p class="figure-label">UAT terlaksana</p>
            </td>
        </tr>
    </table>
    <div class="bar"><div style="width: {{ max(0, min(100, $progress)) }}%;"></div></div>
    @if($showExpected)
        <p class="note">Berdasarkan waktu yang telah berjalan, progres yang diharapkan saat ini sekitar {{ $timeline['expectedProgress'] }}%.</p>
    @endif

    <table class="facts" style="margin-top: 5mm;">
        <tr><td class="k">Klien</td><td class="v">{{ $project->client?->name ?? '—' }}@if($project->client?->pic_name) <span class="muted">&middot; PIC {{ $project->client->pic_name }}</span>@endif</td></tr>
        <tr><td class="k">Pemimpin proyek</td><td class="v">{{ $project->projectLeader?->user?->name ?? '—' }}</td></tr>
        <tr><td class="k">Tim</td><td class="v">{{ $project->teams->pluck('name')->implode(', ') ?: '—' }}</td></tr>
        <tr><td class="k">Jenis proyek</td><td class="v">{{ $typeLabel[$project->type] ?? ($project->type ? ucwords(str_replace('_', ' ', $project->type)) : '—') }}</td></tr>
    </table>

    {{-- Linimasa --}}
    <h2><span class="n">{{ ++$section }}.</span>Linimasa Proyek</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 26%;">Tahap</th>
                <th style="width: 38%;">Periode</th>
                <th style="width: 20%;">Durasi</th>
                <th style="width: 16%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($phaseRows as $key => $name)
                <tr>
                    <td>{{ $name }}</td>
                    <td>{{ $phases[$key]['start'] && $phases[$key]['end'] ? $fmt($phases[$key]['start'], 'd F Y').' – '.$fmt($phases[$key]['end'], 'd F Y') : 'Belum diatur' }}</td>
                    <td>{{ $phases[$key]['length'] ?? '—' }}</td>
                    <td>{{ $phases[$key]['state'] ? $phaseState[$phases[$key]['state']] : '—' }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td>{{ $phases['total']['start'] && $phases['total']['end'] ? $fmt($phases['total']['start'], 'd F Y').' – '.$fmt($phases['total']['end'], 'd F Y') : '—' }}</td>
                <td>{{ $phases['total']['length'] ?? '—' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- UAT --}}
    @if($uat['planned'] || $uat['sessions']->count())
        <h2><span class="n">{{ ++$section }}.</span>User Acceptance Test (UAT)</h2>
        <p style="margin-bottom: 2.5mm;">
            {{ $uat['done'] }}{{ $uat['planned'] ? ' dari '.$uat['planned'].' sesi yang disepakati' : ' sesi' }} telah terlaksana.
            @if($uat['passedRound']) UAT dinyatakan <strong class="ok">lulus</strong> pada sesi ke-{{ $uat['passedRound'] }}.@endif
        </p>
        @if($uat['sessions']->count())
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 10%;">Sesi</th>
                        <th style="width: 18%;">Tanggal</th>
                        <th style="width: 16%;">Hasil</th>
                        <th style="width: 56%;">Temuan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($uat['sessions'] as $session)
                        <tr>
                            <td>{{ $session->round }}@if($uat['planned'] && $session->round > $uat['planned'])<br><span class="small warn">tambahan</span>@endif</td>
                            <td>{{ $fmt($session->date) }}</td>
                            <td class="{{ $uatTone[$session->status] ?? '' }}">{{ $uatResult[$session->status] ?? $session->status }}</td>
                            <td>
                                @forelse($session->findingList() as $i => $finding)
                                    {{ $i + 1 }}. {{ $finding }}@if(! $loop->last)<br>@endif
                                @empty
                                    <span class="muted">Tidak ada temuan</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    {{-- Pekerjaan --}}
    <h2><span class="n">{{ ++$section }}.</span>Rincian Pekerjaan</h2>
    <p style="margin-bottom: 2.5mm;" class="muted">
        {{ $totalTasks }} pekerjaan: {{ $doneTasks->count() }} selesai, {{ $ongoingTasks->count() }} sedang dikerjakan, {{ $pendingTasks->count() }} belum dimulai{{ $cancelledTasks->count() ? ', '.$cancelledTasks->count().' dibatalkan' : '' }}.
    </p>
    @if($totalTasks)
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 7%;">No</th>
                    <th style="width: 51%;">Pekerjaan</th>
                    <th style="width: 26%;">Penanggung jawab</th>
                    <th style="width: 16%;">Tenggat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groups as [$groupName, $tasks])
                    @if($tasks->count())
                        <tr class="group"><td colspan="4">{{ $groupName }} ({{ $tasks->count() }})</td></tr>
                        @foreach($tasks as $task)
                            <tr>
                                <td class="muted">{{ ++$no }}</td>
                                <td>{{ $task->name }}@if($task->status === 'review') <span class="small muted">&middot; ditinjau</span>@endif</td>
                                <td>{{ $assignee($task) }}</td>
                                <td>{{ $fmt($task->due_date) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Belum ada pekerjaan yang dicatat.</p>
    @endif
</body>
</html>
