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
    $employmentLabel = ['full_time' => 'Karyawan tetap', 'contract' => 'Kontrak', 'part_time' => 'Paruh waktu', 'intern' => 'Magang'];
    // Same 0-5 scale the app shows as stars, in words.
    $ratingLabel = [5 => 'Istimewa', 4 => 'Sangat baik', 3 => 'Baik', 2 => 'Cukup', 1 => 'Perlu perbaikan'];
    $roleLabel = ['manager' => 'Manager', 'operational_director' => 'Direktur Operasional', 'finance' => 'Finance Manager', 'hr' => 'HR'];
    $reviewStatus = ['draft' => 'Draf', 'submitted' => 'Diajukan', 'completed' => 'Selesai', 'acknowledged' => 'Dikonfirmasi'];
    $periodName = ['This Month' => 'Bulan ini', 'This Week' => 'Minggu ini', 'All Time' => 'Seluruh masa kerja', 'Tenure' => 'Masa kerja'][$period_label] ?? $period_label;
    $absent = $attendance['absent'] + $attendance['no_record'];
    $excused = $attendance['sick_leave'] + $attendance['leave'];
    $section = 0;
@endphp

    @include('pdf.partials.report-masthead', ['docType' => 'Laporan Kinerja Karyawan', 'docDate' => $generated_at])

    <p class="doc-title">{{ $employee['name'] }}</p>
    <p class="doc-subtitle">
        {{ $employee['job_title'] ?: 'Karyawan' }}@if($employee['team']) &middot; {{ $employee['team'] }}@endif
    </p>
    <p class="doc-meta">
        Periode <strong>{{ $periodName }}</strong> ({{ $fmt($period['start_date']) }} – {{ $fmt($period['end_date']) }})
        &nbsp;&middot;&nbsp; No. karyawan <strong>{{ $employee['code'] ?: '—' }}</strong>
    </p>
    @if(! empty($employee['access_ended_on']))
        <p class="doc-meta bad">
            Akses berakhir {{ $fmt($employee['access_ended_on'], 'd F Y') }} ({{ $employee['access_end_reason'] === 'probation' ? 'masa percobaan selesai' : 'kontrak selesai' }}).
        </p>
    @endif

    {{-- Ringkasan --}}
    <h2><span class="n">{{ ++$section }}.</span>Ringkasan</h2>
    <table class="figures">
        <tr>
            <td style="width: 34%;">
                <p class="figure">{{ $overall_score !== null ? $overall_score.'%' : '—' }}</p>
                <p class="figure-label">Skor keseluruhan{{ $overall_score !== null && isset($ratingLabel[$stars]) ? ' · '.$ratingLabel[$stars] : '' }}</p>
            </td>
            <td style="width: 33%;">
                <p class="figure">{{ $attendance_rate !== null ? $attendance_rate.'%' : '—' }}</p>
                <p class="figure-label">Kehadiran ({{ $attendance['total'] }} hari kerja dinilai)</p>
            </td>
            <td style="width: 33%;">
                <p class="figure">{{ $task_completion_rate !== null ? $task_completion_rate.'%' : '—' }}</p>
                <p class="figure-label">Penyelesaian tugas ({{ $tasks['done'] }} dari {{ $tasks['total'] }})</p>
            </td>
        </tr>
    </table>
    <div class="bar"><div style="width: {{ max(0, min(100, (int) ($overall_score ?? 0))) }}%;"></div></div>

    <table class="facts" style="margin-top: 5mm;">
        <tr><td class="k">Jabatan</td><td class="v">{{ $employee['job_title'] ?: '—' }}</td></tr>
        <tr><td class="k">Tim</td><td class="v">{{ $employee['team'] ?: '—' }}</td></tr>
        <tr><td class="k">Status karyawan</td><td class="v">{{ $employmentLabel[$employee['employment_type'] ?? ''] ?? ucfirst(str_replace('_', ' ', $employee['employment_type'] ?? '—')) }}</td></tr>
        <tr><td class="k">Bergabung</td><td class="v">{{ $fmt($employee['start_date'], 'd F Y') }}</td></tr>
    </table>

    {{-- Kehadiran --}}
    <h2><span class="n">{{ ++$section }}.</span>Kehadiran</h2>
    <table class="data attendance">
        <thead>
            <tr>
                <th class="c" style="width: 14.28%;">Hari kerja</th>
                <th class="c" style="width: 14.28%;">Tepat waktu</th>
                <th class="c" style="width: 14.28%;">Remote</th>
                <th class="c" style="width: 14.28%;">Terlambat</th>
                <th class="c" style="width: 14.28%;">Tidak hadir</th>
                <th class="c" style="width: 14.28%;">Sakit / cuti</th>
                <th class="c" style="width: 14.28%;">Libur</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="c">{{ $attendance['working_days'] }}</td>
                <td class="c">{{ $attendance['present'] }}</td>
                <td class="c">{{ $attendance['remote'] }}</td>
                <td class="c {{ $attendance['late'] ? 'warn' : '' }}">{{ $attendance['late'] }}@if($attendance['late'])<br><span class="small muted">{{ $attendance['late_minutes'] }} menit</span>@endif</td>
                <td class="c {{ $absent ? 'bad' : '' }}">{{ $absent }}</td>
                <td class="c">{{ $excused }}</td>
                <td class="c">{{ $attendance['holidays'] }}</td>
            </tr>
        </tbody>
    </table>
    <p class="note">
        Penilaian per hari kerja: tepat waktu atau remote 100; terlambat hingga 15 menit 90, 16–60 menit 75, lebih dari 60 menit 50; tidak hadir 0.
        Sakit, cuti yang disetujui, dan hari libur tidak dihitung.
        @if($tasks['staff_done_late']) {{ $tasks['staff_done_late'] }} tugas staf diselesaikan melewati tenggat.@endif
    </p>

    {{-- Tugas --}}
    <h2><span class="n">{{ ++$section }}.</span>Tugas yang Diselesaikan</h2>
    @if(count($completed_tasks) > 0)
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 7%;">No</th>
                    <th style="width: 55%;">Tugas</th>
                    <th style="width: 20%;">Sumber</th>
                    <th style="width: 18%;">Tenggat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($completed_tasks as $index => $task)
                    <tr>
                        <td class="muted">{{ $index + 1 }}</td>
                        <td>{{ $task['title'] }}@if($task['project_name'])<br><span class="small muted">{{ $task['project_name'] }}</span>@endif</td>
                        <td>{{ $task['source'] === 'project_task' ? 'Tugas proyek' : 'Tugas staf' }}</td>
                        <td>{{ $fmt($task['due_date']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Tidak ada tugas yang diselesaikan pada periode ini.</p>
    @endif

    {{-- Penilaian kinerja --}}
    @if($performance_review)
        <h2><span class="n">{{ ++$section }}.</span>Penilaian Kinerja Terakhir</h2>
        <table class="facts">
            <tr><td class="k">Periode penilaian</td><td class="v">{{ $performance_review['period'] ?? '—' }}@if($performance_review['period_start'] && $performance_review['period_end']) <span class="muted">({{ $fmt($performance_review['period_start']) }} – {{ $fmt($performance_review['period_end']) }})</span>@endif</td></tr>
            <tr><td class="k">Penilai</td><td class="v">{{ $performance_review['reviewer_name'] ?? '—' }} <span class="muted">&middot; {{ $reviewStatus[$performance_review['status']] ?? ucfirst($performance_review['status']) }}</span></td></tr>
            <tr><td class="k">Nilai keseluruhan</td><td class="v"><strong>{{ $performance_review['overall_rating'] }} / 5</strong></td></tr>
        </table>
        @if(! empty($performance_review['category_scores']))
            <table class="data" style="margin-top: 4mm;">
                <thead>
                    <tr>
                        <th style="width: 70%;">Aspek</th>
                        <th class="num" style="width: 30%;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($performance_review['category_scores'] as $category => $score)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $category)) }}</td>
                            <td class="num">{{ $score }} / 5</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @foreach(['strengths' => 'Kekuatan', 'areas_for_improvement' => 'Hal yang perlu ditingkatkan', 'goals_next_period' => 'Target periode berikutnya'] as $field => $label)
            @if($performance_review[$field])
                <p style="margin-top: 3mm;"><strong>{{ $label }}.</strong> {{ $performance_review[$field] }}</p>
            @endif
        @endforeach
    @endif

    {{-- Catatan penilai --}}
    @if(! empty($reviews))
        <h2><span class="n">{{ ++$section }}.</span>Catatan Penilai</h2>
        @foreach($reviews as $note)
            <div style="page-break-inside: avoid; padding: 0 0 3mm 0; margin-bottom: 3mm; border-bottom: 0.4pt solid #e5e7eb;">
                <p class="small muted" style="margin-bottom: 1mm;">
                    <strong style="color: #1f2933;">{{ $note['reviewer_name'] ?? '—' }}</strong>@if($note['reviewer_role']) &middot; {{ $roleLabel[$note['reviewer_role']] ?? $note['reviewer_role'] }}@endif
                    @if($note['updated_at']) &middot; {{ \Carbon\Carbon::parse($note['updated_at'])->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y') }}@endif
                </p>
                <p style="white-space: pre-line;">{{ $note['review'] }}</p>
            </div>
        @endforeach
    @endif

    {{-- Pengesahan --}}
    <table style="margin-top: 12mm; page-break-inside: avoid;">
        <tr>
            <td style="width: 55%;"></td>
            <td>
                <p>Tangerang Selatan, {{ $generated_at->locale('id')->translatedFormat('d F Y') }}</p>
                <p style="font-weight: bold;">PT. Jendela Cakra Digital</p>
                @include('pdf.partials.signature', ['name' => 'Aldi Pratama Putra, S.Ikom', 'enabled' => $use_signature ?? false])
                <p style="border-top: 0.6pt solid #1f2933; display: inline-block; padding-top: 1mm;">{{ \App\Support\SignatoryName::format('Aldi Pratama Putra, S.Ikom') }}</p>
                <p class="muted">Direktur</p>
            </td>
        </tr>
    </table>
</body>
</html>
