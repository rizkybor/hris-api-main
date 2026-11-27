<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.report-style')
    <style>
        .photo { width: 32mm; height: auto; margin-top: 1.5mm; border: 0.4pt solid #cbd2d9; }
        .sign td { padding: 0; vertical-align: top; }
        .sign .line { border-top: 0.6pt solid #1f2933; display: inline-block; min-width: 55mm; padding-top: 1mm; }
    </style>
</head>
<body>
{{-- Keep to the multi-line PHP block form in this view: Blade mis-parses a
     file that mixes it with the one-line form. --}}
@php
    $fmt = fn ($date, $pattern = 'd F Y') => $date ? \Carbon\Carbon::parse($date)->locale('id')->translatedFormat($pattern) : '—';
    $statusLabel = ['scheduled' => 'Terjadwal', 'in_progress' => 'Berjalan', 'passed' => 'Lulus', 'revision' => 'Perlu revisi'];
    $statusTone = ['passed' => 'ok', 'revision' => 'warn'];
    $items = $uat->locations->flatMap(fn ($location) => $location->items);
    $findings = $items->where('type', 'finding')->count();
    $notes = $items->where('type', 'note')->count();
    $resolved = $items->where('is_resolved', true)->count();
    $planned = $project->uat_rounds_planned;
    $placeName = fn ($place, $n) => $place->location ?: 'Sesi '.$n;
    $isRevision = $kind === 'revision';
    $open = $items->count() - $resolved;
    $section = 0;
@endphp

    @include('pdf.partials.report-masthead', ['docType' => $isRevision ? 'Laporan Hasil Revisi UAT' : 'Laporan Hasil UAT', 'docDate' => $generatedAt])

    <p class="doc-title">{{ $isRevision ? 'Hasil Revisi' : 'Hasil' }} User Acceptance Test (UAT) ke-{{ $uat->round }}</p>
    <p class="doc-subtitle">{{ $project->name }}@if($project->client) &middot; {{ $project->client->name }}@endif</p>
    <p class="doc-meta">
        Tanggal <strong>{{ $fmt($uat->date) }}</strong>
        &nbsp;&middot;&nbsp; Hasil <strong class="{{ $statusTone[$uat->status] ?? '' }}">{{ $statusLabel[$uat->status] ?? $uat->status }}</strong>
        @if($planned)&nbsp;&middot;&nbsp; UAT <strong>{{ $uat->round }} dari {{ $planned }}</strong> sesuai kesepakatan @endif
    </p>

    {{-- Ringkasan --}}
    <h2><span class="n">{{ ++$section }}.</span>Ringkasan</h2>
    <table class="figures">
        <tr>
            @if($isRevision)
                <td style="width: 25%;"><p class="figure">{{ $items->count() }}</p><p class="figure-label">Temuan &amp; catatan</p></td>
                <td style="width: 25%;"><p class="figure ok">{{ $resolved }}</p><p class="figure-label">Sudah direvisi / diupdate</p></td>
                <td style="width: 25%;"><p class="figure {{ $open ? 'warn' : '' }}">{{ $open }}</p><p class="figure-label">Masih dalam proses</p></td>
                <td style="width: 25%;"><p class="figure">{{ $items->count() ? round($resolved / $items->count() * 100) : 0 }}%</p><p class="figure-label">Penyelesaian</p></td>
            @else
                <td style="width: 25%;"><p class="figure">{{ $findings }}</p><p class="figure-label">Temuan</p></td>
                <td style="width: 25%;"><p class="figure">{{ $notes }}</p><p class="figure-label">Catatan</p></td>
                <td style="width: 25%;"><p class="figure">{{ $uat->locations->count() }}</p><p class="figure-label">Sesi</p></td>
                <td style="width: 25%;"><p class="figure {{ $statusTone[$uat->status] ?? '' }}" style="font-size: 13pt;">{{ $statusLabel[$uat->status] ?? $uat->status }}</p><p class="figure-label">Hasil UAT</p></td>
            @endif
        </tr>
    </table>
    @if($uat->notes)
        <table class="facts" style="margin-top: 5mm;">
            <tr><td class="k">Kesimpulan</td><td class="v" style="white-space: pre-line;">{{ $uat->notes }}</td></tr>
        </table>
    @endif

    {{-- Sesi & lokasi --}}
    <h2><span class="n">{{ ++$section }}.</span>Sesi &amp; Lokasi</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 8%;">Sesi</th>
                <th style="width: 30%;">Lokasi</th>
                <th style="width: 20%;">Waktu</th>
                <th style="width: 26%;">Peserta</th>
                <th class="c" style="width: 8%;">Temuan</th>
                <th class="c" style="width: 8%;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($uat->locations as $place)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $place->location ?: '—' }}</td>
                    <td>{{ $fmt($place->held_on ?? $uat->date, 'd M Y') }}@if($place->start_time)<br><span class="small muted">Pukul {{ $place->start_time }}</span>@endif</td>
                    <td>{{ $place->participants ?: '—' }}</td>
                    <td class="c">{{ $place->items->where('type', 'finding')->count() }}</td>
                    <td class="c">{{ $place->items->where('type', 'note')->count() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- One section per session --}}
    @foreach($uat->locations as $place)
        <h2><span class="n">{{ ++$section }}.</span>{{ $isRevision ? 'Hasil Revisi' : 'Temuan & Catatan' }} — {{ $placeName($place, $loop->iteration) }}</h2>
        @if($place->items->count())
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 6%;">No</th>
                        <th style="width: 12%;">Jenis</th>
                        <th style="width: 22%;">Fitur / Menu</th>
                        <th style="width: {{ $isRevision ? '40%' : '60%' }};">Uraian</th>
                        @if($isRevision)<th style="width: 20%;">Status revisi</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($place->items as $item)
                        <tr>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td class="{{ $item->type === 'finding' ? 'warn' : 'muted' }}">{{ $item->type === 'finding' ? 'Temuan' : 'Catatan' }}</td>
                            <td>
                                @if($item->feature || $item->menu)
                                    {{ $item->feature ?: '—' }}@if($item->menu)<br><span class="small muted">Menu: {{ $item->menu }}</span>@endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                {{ $item->description }}
                                @if(isset($photos[$item->id]))
                                    <br><img class="photo" src="{{ $photos[$item->id] }}">
                                @elseif($isRevision ? ($item->photo_path && $item->photo_kind === 'file') : ($item->evidence_path && $item->evidence_kind === 'file'))
                                    <br><span class="small muted">Lampiran: {{ $isRevision ? $item->photo_name : $item->evidence_name }}</span>
                                @endif
                            </td>
                            @if($isRevision)
                                <td class="{{ $item->is_resolved ? 'ok' : 'warn' }}">
                                    @if($item->is_resolved)
                                        {{ $item->type === 'finding' ? 'Sudah direvisi' : 'Sudah diupdate' }}
                                        @if($item->resolved_at)<br><span class="small muted">{{ $fmt($item->resolved_at->copy()->tz('Asia/Jakarta'), 'd M Y') }}</span>@endif
                                    @else
                                        Dalam proses
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">Tidak ada temuan maupun catatan pada sesi ini.</p>
        @endif
    @endforeach

    {{-- Pengesahan --}}
    <table class="sign" style="margin-top: 14mm; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%;">
                <p>Disiapkan oleh,</p>
                <p style="font-weight: bold;">PT. Jendela Cakra Digital</p>
                <div style="height: 20mm;"></div>
                <p class="line">{{ $project->projectLeader?->user?->name ?? ' ' }}</p>
                <p class="muted">{{ $project->projectLeader?->user?->name ? 'Pemimpin Proyek' : 'Nama & jabatan' }}</p>
            </td>
            <td style="width: 50%;">
                <p>{{ $isRevision ? 'Diperiksa dan disetujui oleh,' : 'Diketahui oleh,' }}</p>
                <p style="font-weight: bold;">{{ $project->client?->name ?? 'Klien' }}</p>
                <div style="height: 20mm;"></div>
                <p class="line">{{ $project->client?->pic_name ?? ' ' }}</p>
                <p class="muted">{{ $project->client?->pic_name ? 'PIC Klien' : 'Nama & jabatan' }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
