<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $pageW = $portrait ? '210mm' : '297mm';
        $pageH = $portrait ? '297mm' : '210mm';
    @endphp
    <style>
        /* The certificate itself is full-bleed; the task pages after it get
           ordinary margins so a long list never runs into the paper edge. */
        @page {
            margin: 16mm 16mm 18mm 16mm;
            size: a4 {{ $portrait ? 'portrait' : 'landscape' }};
        }
        @page :first {
            margin: 0;
        }

        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        /*
         * dompdf's pagination counts a block's own declared height as real
         * page-worth of flow -- `.page` at height:210mm already "fills" one
         * physical page, so *any* extra top offset from a margin/padding
         * added on top of it (on the div itself or on a child, regardless
         * of margin-collapse tricks) pushes total flow past 210mm and
         * spills a near-blank second page. The fix is to keep the
         * full-bleed background on its own absolutely positioned layer
         * that never participates in document flow, and let `.page` (the
         * flow container) size itself to its actual content instead of
         * force-declaring a full page of height.
         */
        .page-background {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $pageW }};
            height: {{ $pageH }};
        }

        .page {
            position: relative;
            width: {{ $pageW }};
        }

        /*
         * Small corner ID/copyright/timestamp mark. No background plate --
         * just a metallic-silver gradient fill on the text itself, per
         * design request. Note: this trades away the guaranteed contrast
         * the dark pill gave against arbitrary custom backgrounds -- silver
         * text on a light background (the built-in default template, or a
         * light custom upload) will read faint. Acceptable for the default
         * paper-toned template; a manager picking a very light custom
         * background should be aware this mark may be hard to read on it.
         */
        /* Optional holographic seal: beside the heading on the built-in
           design, bottom-right corner otherwise. */
        .hologram {
            position: absolute;
            @if($modernLayout ?? false)
                right: {{ $portrait ? '16mm' : '26mm' }};
                top: {{ $portrait ? '24mm' : '26mm' }};
                width: {{ $portrait ? '28mm' : '36mm' }};
                height: {{ $portrait ? '28mm' : '36mm' }};
            @else
                right: 22mm;
                bottom: 20mm;
                width: 26mm;
                height: 26mm;
            @endif
        }

        /* Built-in design: left-aligned type beside the geometric column. */
        .m-content {
            position: absolute;
            left: {{ $portrait ? '50mm' : '72mm' }};
            top: {{ $portrait ? '30mm' : '24mm' }};
            width: {{ $portrait ? '145mm' : '200mm' }};
        }
        /* Company line: logo (kept at its 640x542 aspect), a thin gold
           divider, then the name, all centred on one axis. */
        .m-company {
            border-collapse: collapse;
            margin: 0 0 {{ $portrait ? '14mm' : '8mm' }} 0;
        }
        .m-company td {
            padding: 0;
            vertical-align: middle;
        }
        .m-company-logo {
            width: 14.2mm;
            height: 12mm;
        }
        .m-company-divider {
            width: 0;
            height: 9mm;
            border-left: 0.35mm solid #c4a052;
        }
        .m-company-name {
            font-size: 10.5pt;
            font-weight: bold;
            letter-spacing: 0.8px;
            color: #0c1c3c;
            white-space: nowrap;
            line-height: 1.2;
        }
        .m-heading {
            font-size: {{ $portrait ? '40pt' : '48pt' }};
            font-weight: bold;
            color: #0c1c3c;
            line-height: 1;
            margin: 0;
        }
        .m-sub {
            font-size: {{ $portrait ? '10pt' : '11.5pt' }};
            letter-spacing: 5px;
            color: #0c1c3c;
            margin: 1.5mm 0 0 1mm;
        }
        .m-presented {
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1f2937;
            margin: {{ $portrait ? '16mm' : '10mm' }} 0 2mm 0;
        }
        .m-name {
            font-size: {{ $portrait ? '26pt' : '30pt' }};
            font-weight: bold;
            color: #0c51d9;
            margin: 0 0 5mm 0;
        }
        .m-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0c1c3c;
            margin: 0 0 2mm 0;
        }
        .m-desc {
            font-size: 9pt;
            line-height: 1.55;
            color: #4b5563;
            width: {{ $portrait ? '140mm' : '165mm' }};
            margin: 0 0 2mm 0;
        }
        .m-period {
            font-size: 8.5pt;
            color: #6b7280;
            margin: 0;
        }
        .m-footer {
            position: absolute;
            left: {{ $portrait ? '50mm' : '72mm' }};
            bottom: {{ $portrait ? '28mm' : '20mm' }};
            width: {{ $portrait ? '145mm' : '200mm' }};
        }
        .m-footer table {
            width: 100%;
            border-collapse: collapse;
        }
        .m-footer td {
            vertical-align: bottom;
            padding: 0;
        }
        .m-line-value {
            font-size: 10pt;
            font-weight: bold;
            color: #1f2937;
            padding-bottom: 1.5mm !important;
        }
        .m-line {
            border-top: 1px solid #9ca3af;
            padding-top: 1.5mm;
            font-size: 8pt;
            letter-spacing: 1px;
            color: #6b7280;
        }
        .m-sign-title {
            font-size: 8pt;
            color: #6b7280;
        }
        .m-number {
            font-size: 7.5pt;
            letter-spacing: 1px;
            color: #9ca3af;
            margin: 4mm 0 0 0;
        }

        .corner-id-wrap {
            position: absolute;
            top: 6mm;
            right: 8mm;
        }
        .corner-id {
            padding: 0;
            width: 78mm;
            text-align: right;
            font-size: 6.5px;
            line-height: 1.5;
            /*
             * True gradient-filled text (background-clip:text) isn't
             * supported by dompdf -- it silently falls back to a flat,
             * very pale color that's close to invisible against the light
             * default background. A solid metallic-silver tone with a
             * bright highlight shadow approximates the same "silver" look
             * while actually staying legible.
             */
            color: #6b7280;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        /*
         * dompdf has no flexbox and doesn't stretch a display:table's row
         * to the page height (so table-cell vertical-align:middle has no
         * extra space to center within) or resolve auto-height +
         * margin:auto centering on an absolutely positioned box either --
         * both were tried and left the card pinned to the page's top edge.
         * Instead, the panel is absolutely positioned with an equal inset
         * on all four sides (top/right/bottom/left) -- with both top and
         * bottom set and no explicit height, the box's height is simply
         * "whatever's left between them", which dompdf resolves correctly
         * and gives a true equal margin on every side regardless of
         * content length. Being absolutely positioned also keeps it out of
         * normal document flow, so it can never trigger the page-count
         * overflow bug described below on `.page`.
         */
        .content-panel {
            position: absolute;
            top: 16mm;
            right: 16mm;
            bottom: 16mm;
            left: 16mm;
            padding: {{ $portrait ? '34mm 12mm 10mm 12mm' : '10mm 14mm' }};
            background: rgba(255, 255, 255, 0.85);
            border-radius: 3mm;
            text-align: center;
        }

        .seal {
            width: 20mm;
            height: 20mm;
            margin: 0 auto 3mm auto;
        }

        .kicker {
            font-size: 9.5pt;
            letter-spacing: 4px;
            color: #b08d3f;
            font-weight: bold;
            margin: 0 0 3mm 0;
        }

        .cert-heading {
            font-size: 28.5pt;
            font-weight: bold;
            color: #0c1c3c;
            letter-spacing: 3px;
            margin: 0 0 5mm 0;
        }

        .presented-to {
            font-size: 10.5pt;
            color: #6b7280;
            margin: 0 0 3mm 0;
        }

        .recipient-name {
            font-size: 25pt;
            font-weight: bold;
            color: #0c51d9;
            margin: 0 0 2mm 0;
            padding-bottom: 2.5mm;
            border-bottom: 1px solid #b08d3f;
            display: inline-block;
            min-width: {{ $portrait ? '110mm' : '120mm' }};
        }

        .cert-title {
            font-size: 13.5pt;
            font-weight: bold;
            color: #0c1c3c;
            margin: 5mm 0 3mm 0;
        }

        .description {
            font-size: 9.5pt;
            line-height: 1.65;
            color: #374151;
            text-align: center;
            width: {{ $portrait ? '135mm' : '160mm' }};
            margin: 0 auto 3mm auto;
        }

        .period {
            font-size: 8.5pt;
            color: #6b7280;
            margin: 0 0 6mm 0;
        }

        .signature-name {
            display: inline-block;
            font-size: 11.5pt;
            font-weight: bold;
            color: #0c1c3c;
            border-top: 1px solid #1f2937;
            padding-top: 2mm;
            min-width: 65mm;
            /* The blank space above the line (for the actual signature)
               comes from the signature partial right before this. */
            margin: 0 0 1mm 0;
        }

        .signature-title {
            font-size: 8.5pt;
            color: #6b7280;
            margin: 0 0 5mm 0;
        }

        .cert-number {
            font-size: 7.5pt;
            letter-spacing: 1px;
            color: #9ca3af;
            margin: 0;
        }

        /* Page 2+: the recipient's completed tasks. */
        .tasks-page {
            page-break-before: always;
        }
        .tasks-head {
            border-bottom: 2px solid #b08d3f;
            padding-bottom: 3mm;
            margin-bottom: 5mm;
        }
        .tasks-kicker {
            font-size: 8pt;
            letter-spacing: 3px;
            color: #b08d3f;
            font-weight: bold;
            margin: 0 0 1.5mm 0;
        }
        .tasks-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0c1c3c;
            margin: 0 0 1.5mm 0;
        }
        .tasks-meta {
            font-size: 8.5pt;
            color: #6b7280;
            margin: 0;
        }
        table.tasks {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.tasks thead {
            display: table-header-group;
        }
        table.tasks th {
            text-align: left;
            font-size: 7.5pt;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #6b7280;
            background: #f3f4f6;
            padding: 2mm 2.5mm;
        }
        table.tasks td {
            padding: 2mm 2.5mm;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            color: #1f2937;
        }
        table.tasks tr {
            page-break-inside: avoid;
        }
        .col-no {
            width: 8mm;
            color: #9ca3af;
        }
        .col-when {
            white-space: nowrap;
        }
        .task-context {
            color: #6b7280;
        }
        .tasks-empty {
            font-size: 9.5pt;
            color: #6b7280;
            text-align: center;
            padding: 12mm 0;
        }
        .tasks-foot {
            font-size: 7pt;
            color: #9ca3af;
            margin-top: 4mm;
        }
    </style>
</head>
<body>
    <div class="page">
        {{-- An <img>, not a CSS background-image: dompdf re-encodes CSS
             backgrounds as PNG (a 400 KB JPEG became ~1 MB), while an <img>
             JPEG is embedded as is. The image is already A4-landscape shaped
             (CertificateService crops custom backgrounds to fill), so
             stretching it to the page matches the old background-size: cover. --}}
        <img class="page-background" src="{{ $backgroundImagePath }}" alt="">

        @if($modernLayout ?? false)
        <div class="m-content">
            <table class="m-company">
                <tr>
                    <td><img class="m-company-logo" src="{{ public_path('images/jcd-only-color.png') }}" alt=""></td>
                    <td style="width: 4.5mm;"></td>
                    <td><div class="m-company-divider"></div></td>
                    <td style="width: 4.5mm;"></td>
                    <td class="m-company-name">PT. JENDELA CAKRA DIGITAL</td>
                </tr>
            </table>
            <h1 class="m-heading">Sertifikat</h1>
            <p class="m-sub">CERTIFICATE OF ACHIEVEMENT</p>

            <p class="m-presented">SERTIFIKAT INI DIBERIKAN KEPADA</p>
            <p class="m-name">{{ $certificate->recipient_name }}</p>

            <p class="m-title">{{ $certificate->title }}</p>
            @if($certificate->description)
                <p class="m-desc">{{ $certificate->description }}</p>
            @endif
            @if($certificate->start_date || $certificate->end_date)
                <p class="m-period">
                    Periode:
                    {{ $certificate->start_date?->translatedFormat('d F Y') ?? '-' }}
                    @if($certificate->end_date)
                        &ndash; {{ $certificate->end_date->translatedFormat('d F Y') }}
                    @endif
                </p>
            @endif
        </div>

        <div class="m-footer">
            <table>
                <tr>
                    <td style="width: {{ $portrait ? '52mm' : '62mm' }};">
                        <div class="m-line-value">{{ strtoupper($generatedAt->translatedFormat('d F Y')) }}</div>
                        <div class="m-line">TANGGAL TERBIT</div>
                    </td>
                    <td></td>
                    <td style="width: {{ $portrait ? '62mm' : '72mm' }};">
                        @include('pdf.partials.signature', ['name' => $certificate->signatory_name, 'enabled' => $certificate->use_signature, 'space' => 16])
                        <div class="m-line-value">{{ \App\Support\SignatoryName::format($certificate->signatory_name) }}</div>
                        <div class="m-line">{{ strtoupper($certificate->signatory_title) }}</div>
                    </td>
                </tr>
            </table>
            <p class="m-number">No. {{ $certificate->certificate_number }}</p>
        </div>
        @else
        <div class="content-panel">
            <img class="seal" src="{{ public_path('images/jcd-only-color.png') }}">

            <p class="kicker">CERTIFICATE OF ACHIEVEMENT</p>
            <h1 class="cert-heading">SERTIFIKAT</h1>
            <p class="presented-to">Dengan bangga diberikan kepada</p>
            <p class="recipient-name">{{ $certificate->recipient_name }}</p>

            <p class="cert-title">{{ $certificate->title }}</p>

            @if($certificate->description)
                <p class="description">{{ $certificate->description }}</p>
            @endif

            @if($certificate->start_date || $certificate->end_date)
                <p class="period">
                    Periode:
                    {{ $certificate->start_date?->translatedFormat('d F Y') ?? '-' }}
                    @if($certificate->end_date)
                        &ndash; {{ $certificate->end_date->translatedFormat('d F Y') }}
                    @endif
                </p>
            @endif

            @include('pdf.partials.signature', ['name' => $certificate->signatory_name, 'enabled' => $certificate->use_signature, 'space' => 18])
            <p class="signature-name">{{ \App\Support\SignatoryName::format($certificate->signatory_name) }}</p>
            <p class="signature-title">{{ $certificate->signatory_title }}</p>

            <p class="cert-number">{{ $certificate->certificate_number }}</p>
        </div>
        @endif

        @if($certificate->use_hologram)
            <img class="hologram" src="{{ public_path('images/certificate-hologram.png') }}" alt="">
        @endif

        <div class="corner-id-wrap">
            <p class="corner-id">
                &copy; {{ $generatedAt->format('Y') }} {{ $companyName }}<br>
                ID: {{ $certificate->certificate_number }}<br>
                Diterbitkan: {{ $generatedAt->format('d M Y H:i') }}
            </p>
        </div>
    </div>

    @if(is_array($tasks))
        <div class="tasks-page">
            <div class="tasks-head">
                <p class="tasks-kicker">LAMPIRAN SERTIFIKAT</p>
                <p class="tasks-title">Task yang Telah Diselesaikan</p>
                <p class="tasks-meta">
                    {{ $certificate->recipient_name }} &middot; {{ $certificate->certificate_number }}
                    @if($certificate->start_date || $certificate->end_date)
                        &middot; Periode {{ $certificate->start_date?->translatedFormat('d M Y') ?? '-' }}
                        &ndash; {{ $certificate->end_date?->translatedFormat('d M Y') ?? 'sekarang' }}
                    @endif
                    &middot; {{ count($tasks) }} task
                </p>
            </div>

            @if(count($tasks))
                <table class="tasks">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th>Task</th>
                            <th>Proyek / Jenis</th>
                            <th class="col-when">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $i => $task)
                            <tr>
                                <td class="col-no">{{ $i + 1 }}</td>
                                <td>{{ $task['title'] }}</td>
                                <td class="task-context">{{ $task['context'] }}</td>
                                <td class="col-when">{{ $task['completed_at']->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="tasks-foot">Waktu dalam WIB. Waktu selesai task proyek diambil dari pembaruan terakhir task tersebut.</p>
            @else
                <p class="tasks-empty">Belum ada task yang diselesaikan{{ $certificate->start_date || $certificate->end_date ? ' dalam periode ini' : '' }}.</p>
            @endif
        </div>
    @endif
</body>
</html>
