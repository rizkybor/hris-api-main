<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ringkasan Kebutuhan - {{ $screening->code }}</title>
    @php
        $result = $screening->result ?? [];
        $amt = fn ($v) => number_format((float) $v, 0, ',', '.');
        $unitLabel = ['month' => 'Biaya per bulan', 'year' => 'Biaya per tahun'];
        $complexity = ['S' => 'S (sederhana)', 'M' => 'M (menengah)', 'L' => 'L (besar)', 'XL' => 'XL (sangat besar)'][$result['complexity'] ?? ''] ?? '-';
        $fit = ['fit' => 'Sesuai anggaran', 'over' => 'Melebihi anggaran', 'unknown' => 'Belum bisa dibandingkan'][$estimate['budget_fit']] ?? '-';
        $sourceLabel = ['landing' => 'Form website', 'invite' => 'Link undangan', 'internal' => 'Diisi tim JCD'][$screening->source] ?? $screening->source;
    @endphp
    <style>
        /* Same compact look as the meeting-note PDF: Helvetica, JCD navy. */
        @page { margin: 16mm 18mm 18mm 18mm; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2937; font-size: 9.5pt; line-height: 1.45; margin: 0; }

        .brand { width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #0b1d51; }
        .brand td { vertical-align: middle; padding: 0 0 3mm 0; }
        .brand img { height: 9mm; }
        .brand .company { font-size: 8.5pt; font-weight: bold; color: #0b1d51; letter-spacing: 1px; }
        .brand .tagline { font-size: 7.5pt; color: #64748b; }
        .brand .doc { text-align: right; }
        .brand .doc-label { font-size: 8pt; font-weight: bold; color: #0c51d9; letter-spacing: 2px; }
        .brand .doc-number { font-size: 8.5pt; color: #334155; }

        h1 { font-size: 16pt; font-weight: bold; color: #0b1d51; margin: 6mm 0 1mm 0; }
        .sub { color: #64748b; font-size: 9pt; margin: 0 0 4mm 0; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta td { padding: 1.3mm 0; vertical-align: top; font-size: 9pt; border-bottom: 1px solid #eef1f5; }
        .meta .k { width: 34mm; color: #64748b; }

        .section { margin-top: 5mm; }
        .section-title { font-size: 9pt; font-weight: bold; color: #0c51d9; letter-spacing: 1.5px; margin: 0 0 2mm 0; padding-bottom: 1.2mm; border-bottom: 1px solid #d7dce5; text-transform: uppercase; }

        .highlight { background-color: #f2f5fa; border-left: 2px solid #0c51d9; padding: 2.5mm 3mm; margin-bottom: 3mm; }
        .highlight p { margin: 0 0 1mm 0; }
        .highlight .big { font-size: 11pt; font-weight: bold; color: #0b1d51; }

        .qa { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        .qa td { padding: 1.2mm 0; vertical-align: top; border-bottom: 1px solid #f1f3f7; font-size: 9pt; }
        .qa .q { width: 42%; color: #64748b; padding-right: 3mm; }
        .qa-section { font-weight: bold; color: #0b1d51; font-size: 9.5pt; margin: 3mm 0 1mm 0; page-break-after: avoid; }
        .qa-block { page-break-inside: avoid; }

        .items { width: 100%; border-collapse: collapse; font-size: 9pt; line-height: 1.35; }
        .items th { background-color: #0b1d51; color: #ffffff; font-weight: bold; text-align: left; padding: 1.6mm 2mm; }
        .items td { border-bottom: 1px solid #e5e9f0; padding: 1.6mm 2mm; vertical-align: top; }
        .items .no { width: 6mm; color: #64748b; }
        .items .num { text-align: right; white-space: nowrap; }
        /* One price column: "Rp." pinned left, figure right-aligned to the
           table edge (same as the PO/letter tables). Billing period goes
           under the service name instead of after the figure. */
        .items th.price { width: 34mm; text-align: right; }
        .items td.money { text-align: right; white-space: nowrap; }
        .items td.money .rp { float: left; }
        .items .muted { color: #64748b; font-size: 8pt; }
        .items tfoot td { font-weight: bold; border-top: 1.5px solid #0b1d51; border-bottom: none; }

        ul.flags { margin: 0; padding-left: 5mm; }
        ul.flags li { margin-bottom: 1mm; }
        .note { color: #64748b; font-size: 8pt; margin-top: 2mm; }
        /* Fixed in the bottom margin so it repeats on every page instead of
           spilling onto a page of its own. */
        .footer { position: fixed; bottom: -11mm; left: 0; right: 0; color: #94a3b8; font-size: 7.5pt; border-top: 1px solid #e5e9f0; padding-top: 2mm; }
    </style>
</head>
<body>
    <table class="brand">
        <tr>
            <td style="width: 12mm;"><img src="{{ public_path('images/jcd-logo-pdf.png') }}" alt="JCD"></td>
            <td>
                <div class="company">PT JENDELA CAKRA DIGITAL</div>
                <div class="tagline">Software House &amp; Digital Agency</div>
            </td>
            <td class="doc">
                <div class="doc-label">RINGKASAN KEBUTUHAN{{ $withPrices ? ' · INTERNAL' : '' }}</div>
                <div class="doc-number">{{ $screening->code }}</div>
            </td>
        </tr>
    </table>

    <h1>{{ $screening->company ?: ($screening->contact_name ?: 'Calon Klien') }}</h1>
    <p class="sub">Hasil screening kebutuhan sistem · {{ ($screening->submitted_at ?? $screening->created_at)->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</p>

    <table class="meta">
        <tr><td class="k">Nama kontak</td><td>{{ $screening->contact_name ?: '-' }}</td></tr>
        <tr><td class="k">Perusahaan</td><td>{{ $screening->company ?: '-' }}</td></tr>
        <tr><td class="k">Email / Telepon</td><td>{{ collect([$screening->email, $screening->phone])->filter()->implode(' · ') ?: '-' }}</td></tr>
        <tr><td class="k">Sumber</td><td>{{ $sourceLabel }}</td></tr>
    </table>

    <div class="section">
        <div class="section-title">Rekomendasi Sistem</div>
        <div class="highlight">
            <p class="big">{{ implode(', ', $result['system_types'] ?? []) ?: 'Perlu dikonsultasikan lebih lanjut' }}</p>
            <p>Tingkat kompleksitas: <strong>{{ $complexity }}</strong></p>
            @if($estimate['longest_duration'] || $estimate['extra_weeks'])
                <p>Perkiraan waktu: <strong>{{ $estimate['longest_duration'] ?: '-' }}</strong>@if($estimate['extra_weeks']) + sekitar {{ $estimate['extra_weeks'] }} minggu untuk modul/fitur tambahan @endif</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Layanan yang Direkomendasikan</div>
        @if($items->isEmpty())
            <p class="note">Belum ada layanan yang direkomendasikan.</p>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th class="no">No</th>
                        <th>Layanan</th>
                        <th>Estimasi Waktu</th>
                        @if($withPrices)<th class="price">Estimasi Harga</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                        <tr>
                            <td class="no">{{ $i + 1 }}</td>
                            <td>
                                {{ $item->name }}@if((float) $item->qty > 1) &times; {{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }}@endif
                                @if($withPrices && isset($unitLabel[$item->price_unit]))<div class="muted">{{ $unitLabel[$item->price_unit] }}</div>@endif
                                @if($item->hours)<div class="muted">± {{ rtrim(rtrim(number_format((float) $item->hours, 1, ',', '.'), '0'), ',') }} jam kerja</div>@endif
                            </td>
                            <td>{{ $item->duration_text ?: ($item->hours ? '-' : '-') }}</td>
                            @if($withPrices)
                                @if($item->unit_price === null)
                                    <td class="num"><span class="muted">Sesuai penawaran</span></td>
                                @else
                                    <td class="money"><span class="rp">Rp.</span>{{ $amt($item->lineTotal()) }}</td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                @if($withPrices)
                    <tfoot>
                        <tr><td></td><td colspan="2">Estimasi biaya sekali bayar (mulai dari)</td><td class="money"><span class="rp">Rp.</span>{{ $amt($estimate['one_time']) }}</td></tr>
                        @if($estimate['monthly'])<tr><td></td><td colspan="2">Biaya bulanan</td><td class="money"><span class="rp">Rp.</span>{{ $amt($estimate['monthly']) }}</td></tr>@endif
                        @if($estimate['yearly'])<tr><td></td><td colspan="2">Biaya tahunan</td><td class="money"><span class="rp">Rp.</span>{{ $amt($estimate['yearly']) }}</td></tr>@endif
                    </tfoot>
                @endif
            </table>
            @if($withPrices)
                <p class="note">
                    Harga mengikuti Rate Card JCD ("start from"); modul/fitur tambahan dihitung dari estimasi jam kerja.
                    @if($estimate['by_quote']->isNotEmpty()) Sesuai penawaran: {{ $estimate['by_quote']->implode(', ') }}. @endif
                    @if(!empty($result['budget'])) Anggaran klien: {{ preg_replace('/\bRp\s(?=\d)/', 'Rp. ', $result['budget']['label']) }} — {{ $fit }}.@endif
                    @if($estimate['deferrable']) Bisa ditunda ke tahap berikutnya: {{ implode(', ', $estimate['deferrable']) }}. @endif
                </p>
            @endif
        @endif
    </div>

    @if($withPrices && !empty($result['flags']))
        <div class="section">
            <div class="section-title">Catatan untuk Tim</div>
            <ul class="flags">
                @foreach($result['flags'] as $flag)<li>{{ $flag }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="section">
        @foreach($sections as $section)
            {{-- Title rides inside the first block so it can't be stranded at a page bottom. --}}
            <div class="qa-block">
                @if($loop->first)<div class="section-title">Jawaban Klien</div>@endif
                <div class="qa-section">{{ $section['title'] }}</div>
                <table class="qa">
                    @foreach($section['answers'] as $a)
                        <tr><td class="q">{{ $a['question'] }}</td><td>{!! nl2br(e($a['answer'])) !!}</td></tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    </div>

    <div class="footer">
        PT Jendela Cakra Digital · jcdigital.co.id · Dokumen ini adalah ringkasan awal kebutuhan dan bukan penawaran resmi.
    </div>
</body>
</html>
