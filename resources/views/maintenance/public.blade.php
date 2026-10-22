@php
    $h = fn ($hours) => $service->hours((float) $hours);
    $monthLabel = fn ($date) => $date->copy()->locale('id')->translatedFormat('F Y');
    $dateLabel = fn ($date) => $date ? \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j M Y') : '-';
    $categories = [
        'bug_fix' => 'Perbaikan bug',
        'content_update' => 'Update konten',
        'minor_feature' => 'Fitur kecil',
        'server' => 'Server & infrastruktur',
        'consultation' => 'Konsultasi',
        'other' => 'Lainnya',
    ];
    $featureStatus = [
        'proposed' => ['Diusulkan', 'st-gray'],
        'approved' => ['Disetujui', 'st-blue'],
        'in_progress' => ['Dikerjakan', 'st-amber'],
        'done' => ['Selesai', 'st-green'],
    ];
    $policyText = [
        'bank' => 'dialihkan ke Saldo Pengembangan',
        'rollover' => 'diakumulasi ke bulan berikutnya',
        'expire' => 'hangus',
    ];
    $leftoverText = function ($p) use ($h) {
        if (! $p->isClosed()) {
            return 'Berjalan';
        }
        if (! $p->leftover_hours) {
            $over = $p->totals()['overage'];
            return $over > 0 ? 'Melebihi kuota '.$h($over) : 'Terpakai habis';
        }
        return match ($p->leftover_applied) {
            'bank' => $p->leftover_banked_hours < $p->leftover_hours
                ? $h($p->leftover_banked_hours).' ke Saldo · '.$h($p->leftover_hours - $p->leftover_banked_hours).' hangus (batas saldo)'
                : 'Dialihkan ke Saldo Pengembangan',
            'rollover' => 'Diakumulasi ke bulan berikutnya',
            default => 'Hangus',
        };
    };
    $bankType = [
        'leftover' => 'Sisa kuota', 'adjust_in' => 'Penambahan', 'refund' => 'Pengembalian',
        'feature' => 'Fitur baru', 'adjust_out' => 'Pengurangan', 'expire' => 'Kedaluwarsa',
    ];
    $pct = $totals ? min(100, $totals['used_percent']) : 0;
    $barColor = $totals && $totals['overage'] > 0 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#0c51d9');
    $policy = $period?->leftover_action ?? $contract->leftover_policy;

    // Link previews (WhatsApp, email, Slack): what the page holds, in one
    // line -- a snapshot of when the link was opened, the page is live.
    $metaTitle = 'Kuota Maintenance · '.$contract->name;
    $metaParts = [($contract->client?->name ? $contract->client->name.' · ' : '').$contract->name.'.'];
    if ($totals) {
        $metaParts[] = 'Sisa kuota '.$monthLabel($period->period_month).': '.$h($totals['remaining']).' dari '.$h($totals['total'])
            .($totals['overage'] > 0 ? ' (melebihi '.$h($totals['overage']).')' : '').'.';
    } else {
        $metaParts[] = 'Kuota '.$h($contract->monthly_quota_hours).' per bulan.';
    }
    $metaParts[] = 'Saldo Pengembangan '.$h($balance).'.';
    $metaParts[] = 'Lihat daftar pekerjaan, fitur baru, dan riwayat bulanan dari PT Jendela Cakra Digital.';
    $metaDescription = \Illuminate\Support\Str::limit(implode(' ', $metaParts), 300);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="PT Jendela Cakra Digital">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $contract->shareUrl() }}">
    <meta property="og:image" content="{{ asset('images/jcd-logo-pdf.png') }}">
    <meta property="og:image:alt" content="Logo PT Jendela Cakra Digital">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ asset('images/jcd-logo-pdf.png') }}">
    <link rel="icon" href="{{ asset('images/jcd-logo-pdf.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.55; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px 16px 40px; }
        a { color: #0c51d9; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { height: 34px; }
        .brand .name { font-size: 12px; font-weight: 700; letter-spacing: 1.2px; color: #0b1d51; }
        .brand .tag { font-size: 11px; color: #64748b; }
        .label { font-size: 11px; font-weight: 700; letter-spacing: 1.6px; color: #0c51d9; text-transform: uppercase; }

        .card { background: #fff; border: 1px solid #dfe3ea; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .hero h1 { font-size: 22px; line-height: 1.3; color: #0b1d51; margin: 4px 0 4px; }
        .muted { color: #64748b; }
        .small { font-size: 12px; }

        .month-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .month-row h2 { font-size: 16px; margin: 0; color: #0b1d51; }
        select { font: inherit; padding: 7px 10px; border: 1px solid #dfe3ea; border-radius: 8px; background: #fff; color: #1f2937; }

        .big { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
        .big .num { font-size: 34px; font-weight: 800; color: #0b1d51; line-height: 1.1; }
        .bar { height: 12px; background: #eef1f5; border-radius: 99px; overflow: hidden; margin: 12px 0 8px; }
        .bar span { display: block; height: 100%; border-radius: 99px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 14px; }
        .stat { border: 1px solid #eef1f5; border-radius: 10px; padding: 10px 12px; }
        .stat .k { font-size: 11px; color: #64748b; }
        .stat .v { font-size: 16px; font-weight: 700; color: #0b1d51; }
        .notice { margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 13px; }
        .n-blue { background: #eff4ff; color: #1e3a8a; }
        .n-red { background: #fef2f2; color: #991b1b; }
        .n-amber { background: #fffbeb; color: #92400e; }

        h3 { font-size: 15px; color: #0b1d51; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; letter-spacing: .6px; text-transform: uppercase; color: #64748b; font-weight: 700; padding: 8px 8px; border-bottom: 1px solid #dfe3ea; }
        td { padding: 10px 8px; border-bottom: 1px solid #eef1f5; vertical-align: top; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .chip { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #f1f5f9; color: #475569; }
        .st-gray { background: #f1f5f9; color: #475569; } .st-blue { background: #eff4ff; color: #0c51d9; }
        .st-amber { background: #fffbeb; color: #b45309; } .st-green { background: #ecfdf5; color: #047857; }
        .pos { color: #047857; font-weight: 700; } .neg { color: #b91c1c; font-weight: 700; }
        .empty { color: #94a3b8; font-style: italic; padding: 6px 0; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid2 .card { margin-bottom: 0; }
        .foot { text-align: center; font-size: 12px; color: #64748b; margin-top: 24px; }

        @media (max-width: 720px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .grid2 { grid-template-columns: 1fr; }
            .hide-sm { display: none; }
            .big .num { font-size: 28px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="brand">
            <img src="{{ asset('images/jcd-logo-pdf.png') }}" alt="">
            <div>
                <div class="name">PT JENDELA CAKRA DIGITAL</div>
                <div class="tag">Software House &amp; Digital Agency</div>
            </div>
        </div>
        <div class="label hide-sm">Kuota Maintenance</div>
    </div>

    <div class="card hero">
        <div class="label">{{ $contract->client?->name }}</div>
        <h1>{{ $contract->name }}</h1>
        <div class="muted small">
            @if($contract->project) Project {{ $contract->project->name }} · @endif
            Kuota {{ $h($contract->monthly_quota_hours) }} per bulan
            @if($contract->status === 'paused') · <strong>Kontrak sedang dijeda</strong> @elseif($contract->status === 'ended') · <strong>Kontrak telah berakhir</strong> @endif
        </div>
    </div>

    @if($period)
        <div class="card">
            <div class="month-row">
                <h2>{{ $isCurrent ? 'Bulan ini · ' : '' }}{{ $monthLabel($period->period_month) }}</h2>
                @if($periods->count() > 1)
                    <form method="get">
                        <select name="bulan" onchange="this.form.submit()" aria-label="Pilih bulan">
                            @foreach($periods as $p)
                                <option value="{{ $p->period_month->format('Y-m') }}" @selected($p->id === $period->id)>{{ $monthLabel($p->period_month) }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>

            <div class="big">
                <span class="num">{{ $h($totals['remaining']) }}</span>
                <span class="muted">tersisa dari {{ $h($totals['total']) }}</span>
            </div>
            <div class="bar"><span style="width: {{ $pct }}%; background: {{ $barColor }};"></span></div>
            <div class="small muted">{{ $h($totals['used']) }} terpakai ({{ rtrim(rtrim(number_format($totals['used_percent'], 1, ',', ''), '0'), ',') }}%)</div>

            <div class="stats">
                <div class="stat"><div class="k">Kuota bulanan</div><div class="v">{{ $h($totals['quota']) }}</div></div>
                <div class="stat"><div class="k">Akumulasi bulan lalu</div><div class="v">{{ $h($totals['carried']) }}</div></div>
                <div class="stat"><div class="k">Penyesuaian</div><div class="v">{{ $totals['adjusted'] > 0 ? '+' : '' }}{{ $h($totals['adjusted']) }}</div></div>
                <div class="stat"><div class="k">Terpakai</div><div class="v">{{ $h($totals['used']) }}</div></div>
            </div>

            @if($totals['overage'] > 0)
                <div class="notice n-red">Pemakaian melebihi kuota sebanyak <strong>{{ $h($totals['overage']) }}</strong>.</div>
            @elseif($isCurrent && $totals['remaining'] > 0)
                <div class="notice n-blue">Sisa kuota bulan ini akan <strong>{{ $policyText[$policy] ?? 'hangus' }}</strong> di akhir bulan.</div>
            @elseif(! $isCurrent)
                <div class="notice n-blue">Sisa kuota bulan ini: <strong>{{ $leftoverText($period) }}</strong>.</div>
            @endif
        </div>

        <div class="card">
            <h3>Pekerjaan {{ $monthLabel($period->period_month) }}</h3>
            @if($logs->isEmpty())
                <div class="empty">Belum ada pekerjaan yang dicatat pada bulan ini.</div>
            @else
                <table>
                    <thead><tr><th>Tanggal</th><th>Pekerjaan</th><th class="hide-sm">Kategori</th><th class="num">Durasi</th></tr></thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td style="white-space: nowrap;">{{ $dateLabel($log->work_date) }}</td>
                                <td>
                                    <strong>{{ $log->title }}</strong>
                                    @if($log->description)<div class="small muted">{{ $log->description }}</div>@endif
                                </td>
                                <td class="hide-sm">@if($log->category)<span class="chip">{{ $categories[$log->category] ?? $log->category }}</span>@endif</td>
                                <td class="num">{{ $h($log->hours) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @else
        <div class="card"><div class="empty">Belum ada periode maintenance yang berjalan.</div></div>
    @endif

    <div class="grid2">
        <div class="card">
            <h3>Saldo Pengembangan</h3>
            <div class="big"><span class="num">{{ $h($balance) }}</span></div>
            <div class="small muted" style="margin-top: 6px;">
                Sisa kuota yang tidak terpakai dapat digunakan untuk membuat fitur baru.
                @if($contract->bank_expiry_months) Jam di saldo berlaku {{ $contract->bank_expiry_months }} bulan, setelah itu hangus jika tidak digunakan.@endif
            </div>
            @if($expiring['hours'] > 0)
                <div class="notice n-amber">{{ $h($expiring['hours']) }} akan hangus mulai {{ $dateLabel($expiring['first_date']) }} jika tidak digunakan.</div>
            @endif
            @if($bankEntries->isNotEmpty())
                <table style="margin-top: 12px;">
                    <tbody>
                        @foreach($bankEntries as $entry)
                            <tr>
                                <td>
                                    {{ $bankType[$entry->type] ?? $entry->type }}
                                    <div class="small muted">{{ $entry->note }}</div>
                                </td>
                                <td class="num">
                                    <span class="{{ $entry->hours >= 0 ? 'pos' : 'neg' }}">{{ $entry->hours >= 0 ? '+' : '−' }}{{ $h(abs($entry->hours)) }}</span>
                                    <div class="small muted">{{ $dateLabel($entry->created_at) }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h3>Fitur Baru</h3>
            @if($features->isEmpty())
                <div class="empty">Belum ada fitur baru dari Saldo Pengembangan.</div>
            @else
                <table>
                    <tbody>
                        @foreach($features as $feature)
                            <tr>
                                <td>
                                    <strong>{{ $feature->title }}</strong>
                                    <div style="margin-top: 3px;"><span class="chip {{ $featureStatus[$feature->status][1] ?? '' }}">{{ $featureStatus[$feature->status][0] ?? $feature->status }}</span></div>
                                </td>
                                <td class="num">
                                    {{ $h($feature->usedHours()) }}
                                    <div class="small muted">dari estimasi {{ $h($feature->estimated_hours) }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($periods->count() > 1)
        <div class="card" style="margin-top: 16px;">
            <h3>Riwayat Bulanan</h3>
            <table>
                <thead><tr><th>Bulan</th><th class="num">Kuota</th><th class="num">Terpakai</th><th class="hide-sm">Sisa kuota</th></tr></thead>
                <tbody>
                    @foreach($periods as $p)
                        @php $t = $p->totals(); @endphp
                        <tr>
                            <td><a href="?bulan={{ $p->period_month->format('Y-m') }}">{{ $monthLabel($p->period_month) }}</a></td>
                            <td class="num">{{ $h($t['total']) }}</td>
                            <td class="num">{{ $h($t['used']) }}</td>
                            <td class="hide-sm">{{ $leftoverText($p) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="foot">
        Diperbarui {{ now('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') }} WIB ·
        Pertanyaan? <a href="mailto:contact@jcdigital.co.id">contact@jcdigital.co.id</a> · WhatsApp 0878 8279 2511
    </div>
</div>
</body>
</html>
