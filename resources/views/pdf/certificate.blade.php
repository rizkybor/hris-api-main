<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            size: a4 landscape;
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
            width: 297mm;
            height: 210mm;
            background-image: url('{{ $backgroundImagePath }}');
            background-size: cover;
            background-position: center;
        }

        .page {
            position: relative;
            width: 297mm;
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
            padding: 10mm 14mm;
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
            font-size: 10px;
            letter-spacing: 4px;
            color: #b08d3f;
            font-weight: bold;
            margin: 0 0 3mm 0;
        }

        .cert-heading {
            font-size: 30px;
            font-weight: bold;
            color: #0c1c3c;
            letter-spacing: 3px;
            margin: 0 0 5mm 0;
        }

        .presented-to {
            font-size: 11px;
            color: #6b7280;
            margin: 0 0 3mm 0;
        }

        .recipient-name {
            font-size: 26px;
            font-weight: bold;
            color: #0c51d9;
            margin: 0 0 2mm 0;
            padding-bottom: 2.5mm;
            border-bottom: 1px solid #b08d3f;
            display: inline-block;
            min-width: 120mm;
        }

        .cert-title {
            font-size: 14px;
            font-weight: bold;
            color: #0c1c3c;
            margin: 5mm 0 3mm 0;
        }

        .description {
            font-size: 10px;
            line-height: 1.65;
            color: #374151;
            text-align: center;
            width: 160mm;
            margin: 0 auto 3mm auto;
        }

        .period {
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 6mm 0;
        }

        .signature-name {
            display: inline-block;
            font-size: 12px;
            font-weight: bold;
            color: #0c1c3c;
            border-top: 1px solid #1f2937;
            padding-top: 2mm;
            min-width: 65mm;
            /* Blank space above the line for an actual signature to be
               placed, not just a hairline sitting right under the last
               paragraph of text. */
            margin: 18mm 0 1mm 0;
        }

        .signature-title {
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 5mm 0;
        }

        .cert-number {
            font-size: 8px;
            letter-spacing: 1px;
            color: #9ca3af;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-background"></div>

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

            <p class="signature-name">{{ $certificate->signatory_name }}</p>
            <p class="signature-title">{{ $certificate->signatory_title }}</p>

            <p class="cert-number">{{ $certificate->certificate_number }}</p>
        </div>

        <div class="corner-id-wrap">
            <p class="corner-id">
                &copy; {{ $generatedAt->format('Y') }} {{ $companyName }}<br>
                ID: {{ $certificate->certificate_number }}<br>
                Diterbitkan: {{ $generatedAt->format('d M Y H:i') }}
            </p>
        </div>
    </div>
</body>
</html>
