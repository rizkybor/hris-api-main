<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 15mm 17mm 15mm; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 9pt; }
        .top-bar { position: fixed; top: -18mm; left: -15mm; right: -15mm; height: 10mm; background-color: #0b1d51; }
        .bottom-bar { position: fixed; bottom: -17mm; left: -15mm; right: -15mm; height: 5mm; background-color: #0b1d51; }
        .watermark { position: fixed; top: 92mm; left: 35mm; width: 110mm; height: 93mm; opacity: 0.05; }
        .footer { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 8pt; color: #0b1d51; }
        table { width: 100%; border-collapse: collapse; }
        .brand { font-size: 13pt; font-weight: bold; color: #0b1d51; margin: 0; }
        .eyebrow { font-size: 8pt; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase; margin: 0; }
        .title { font-size: 22pt; font-weight: bold; color: #0b1d51; margin: 2mm 0 0 0; }
        .year { font-size: 40pt; font-weight: bold; color: #2563eb; margin: 0; line-height: 1; }
        .stats td { border: 1px solid #e2e8f0; padding: 6px 8px; width: 33%; }
        .stat-value { font-size: 14pt; font-weight: bold; color: #0b1d51; margin: 0; }
        .stat-label { font-size: 7.5pt; color: #64748b; margin: 2px 0 0 0; text-transform: uppercase; letter-spacing: 0.3px; }
        .section-title { color: #0b1d51; font-size: 10pt; font-weight: bold; margin: 7mm 0 2mm 0; text-transform: uppercase; letter-spacing: 0.3px; }
        .toc th { border-bottom: 1.5px solid #0b1d51; color: #0b1d51; text-align: left; padding: 4px 4px; font-size: 8.5pt; }
        .toc td { border-bottom: 1px solid #e5e7eb; padding: 4px 4px; font-size: 8.5pt; vertical-align: top; }
        .muted { color: #64748b; }
        .num { font-family: "Courier", monospace; font-size: 8pt; }
        .right { text-align: right; }
        .empty { color: #94a3b8; font-style: italic; padding: 8px 4px; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="bottom-bar"></div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">

    <table>
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <p class="eyebrow">Arsip Dokumen Tahunan</p>
                <p class="brand">PT. JENDELA CAKRA DIGITAL</p>
                <p class="title">{{ $label }}</p>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <p class="eyebrow">Tahun</p>
                <p class="year">{{ $year }}</p>
            </td>
        </tr>
    </table>

    <table class="stats" style="margin-top: 6mm;">
        <tr>
            <td><p class="stat-value">{{ count($rows) }}</p><p class="stat-label">Dokumen</p></td>
            <td><p class="stat-value">{{ $totalPages }}</p><p class="stat-label">Halaman (termasuk sampul)</p></td>
            <td><p class="stat-value" style="font-size: 10pt;">{{ $generatedAt->translatedFormat('d F Y, H:i') }} WIB</p><p class="stat-label">Dibuat</p></td>
        </tr>
    </table>

    <p class="section-title">Daftar Isi</p>
    <table class="toc">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 27%;">Nomor</th>
                <th style="width: 13%;">Tanggal</th>
                <th style="width: 45%;">Perihal / Pihak</th>
                <th style="width: 10%;" class="right">Hal.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="num">{{ $row['number'] }}</td>
                    <td>{{ $row['date']->locale('id')->translatedFormat('d M Y') }}</td>
                    <td>
                        {{ $row['title'] ?: '—' }}
                        @if($row['extra'])<br><span class="muted">{{ $row['extra'] }}</span>@endif
                    </td>
                    <td class="right">{{ $row['page'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Tidak ada dokumen pada tahun ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($skipped))
        <p class="section-title" style="color: #b91c1c;">Tidak dapat disertakan ({{ count($skipped) }})</p>
        <p class="muted" style="margin: 0 0 2mm 0;">File PDF dokumen berikut gagal dibuat atau diunduh saat arsip disusun. Unduh satu per satu dari menunya masing-masing.</p>
        <table class="toc">
            @foreach($skipped as $row)
                <tr><td class="num" style="width: 32%;">{{ $row['number'] }}</td><td>{{ $row['title'] ?: '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td style="width: 33%;">contact@jcdigital.co.id</td>
                <td style="width: 34%; text-align: center;">www.jcdigital.co.id</td>
                <td style="width: 33%; text-align: right;">Phone +62 878-8279-2511</td>
            </tr>
        </table>
    </div>
</body>
</html>
