<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->documentTitle() }}</title>
    @php
        $date = $proposal->proposal_date->copy()->locale('id')->translatedFormat('j F Y');
        // Numbered (titled) sections, for the table of contents.
        $toc = [];
        $n = 0;
        foreach ($sections as $s) {
            if (trim($s['title'] ?? '') !== '') {
                $toc[] = [++$n, $s['title']];
            }
        }
        // Splits section HTML into its first top-level block and the rest.
        // A very tall first block (e.g. a big table) isn't kept with the
        // heading, so it can still break across pages.
        $splitFirstBlock = function (string $html): array {
            $html = trim($html);
            if (! preg_match('#^\s*<(p|h[1-4]|ul|ol|blockquote|table|div)\b[^>]*>.*?</\1>#is', $html, $m)) {
                return ['', $html];
            }
            $first = $m[0];
            $isTall = preg_match('#^\s*<table#i', $first) && substr_count(strtolower($first), '<tr') > 6;
            return $isTall ? ['', $html] : [$first, substr($html, strlen($first))];
        };
    @endphp
    <style>
        /* Plain, print-first layout: solid JCD navy and blue only (no
           gradients or decorative effects), generous white space, a
           dedicated cover page, and a thin running header/footer on the
           content pages drawn by ProposalController::addPageChrome. */
        @page { margin: 24mm 22mm 22mm 22mm; }

        body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2937; font-size: 10.5pt; line-height: 1.55; margin: 0; }

        /* ---------- Cover ---------- */
        .cover { position: relative; height: 247mm; page-break-after: always; }
        .cover-brand { width: 100%; border-bottom: 1px solid #d7dce5; padding-bottom: 4mm; }
        .cover-brand td { vertical-align: middle; padding: 0; }
        .cover-brand img { height: 11mm; }
        .cover-brand .company { font-size: 9pt; font-weight: bold; color: #0b1d51; letter-spacing: 1.2px; }
        .cover-brand .tagline { font-size: 8pt; color: #64748b; }

        .cover-eyebrow { margin: 26mm 0 4mm 0; font-size: 9pt; font-weight: bold; color: #0c51d9; letter-spacing: 3px; }
        .cover-rule { width: 18mm; height: 1.2mm; background-color: #0c51d9; margin-bottom: 7mm; }
        .cover-title { font-size: 28pt; line-height: 1.15; font-weight: bold; color: #0b1d51; margin: 0 0 8mm 0; }
        .cover-label { font-size: 8.5pt; color: #64748b; letter-spacing: 1px; margin: 0 0 1mm 0; }
        .cover-client { font-size: 15pt; font-weight: bold; color: #1f2937; margin: 0; }

        .cover-image { margin-top: 12mm; height: 62mm; overflow: hidden; border-radius: 1.5mm; }
        .cover-image img { width: 100%; }

        .cover-meta { position: absolute; bottom: 0; left: 0; width: 100%; border-top: 1px solid #d7dce5; }
        .cover-meta td { width: 33.33%; vertical-align: top; padding: 4mm 4mm 0 0; }
        .cover-meta .k { font-size: 7.5pt; color: #64748b; letter-spacing: 1px; margin: 0 0 1mm 0; }
        .cover-meta .v { font-size: 9.5pt; color: #0b1d51; font-weight: bold; margin: 0; }

        /* ---------- Table of contents ---------- */
        .toc { margin-bottom: 9mm; }
        .toc-title { font-size: 9pt; font-weight: bold; color: #0c51d9; letter-spacing: 2px; margin: 0 0 3mm 0; }
        .toc table { width: 100%; border-collapse: collapse; }
        .toc td { padding: 1.6mm 0; border-bottom: 1px solid #eef1f5; font-size: 10pt; vertical-align: top; }
        .toc .num { width: 9mm; color: #0c51d9; font-weight: bold; }

        /* ---------- Sections ---------- */
        .section { margin-top: 8mm; }
        .section-break { page-break-before: always; margin-top: 0; }
        .section-head { width: 100%; border-collapse: collapse; margin-bottom: 3.5mm; }
        .section-head td { padding: 0 0 2mm 0; border-bottom: 1.5px solid #0b1d51; vertical-align: bottom; }
        .section-head .num { width: 10mm; font-size: 13pt; font-weight: bold; color: #0c51d9; }
        .section-head .title { font-size: 13pt; font-weight: bold; color: #0b1d51; }

        .content p { margin: 0 0 2.6mm 0; }
        .content ul, .content ol { margin: 0 0 3mm 0; padding-left: 6mm; }
        .content li { margin-bottom: 1.3mm; }
        .content h1 { font-size: 14pt; font-weight: bold; color: #0b1d51; margin: 5mm 0 2mm 0; }
        .content h2 { font-size: 12pt; font-weight: normal; color: #475569; margin: 4mm 0 2mm 0; }
        .content h3 { font-size: 11pt; font-weight: bold; color: #0b1d51; margin: 5mm 0 1.8mm 0; }
        .content h4 { font-size: 10.5pt; font-weight: bold; color: #334155; margin: 3.5mm 0 1.5mm 0; }
        .content a { color: #0c51d9; }
        .content hr { border: none; border-top: 1px solid #d7dce5; margin: 4mm 0; }
        .content img { max-width: 100%; margin: 2mm 0; }

        .content table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm 0; font-size: 9.5pt; line-height: 1.4; }
        .content th, .content td { border: 1px solid #d7dce5; padding: 1.8mm 2.4mm; vertical-align: top; text-align: left; }
        .content th { background-color: #0b1d51; border-color: #0b1d51; color: #ffffff; font-weight: bold; }
        .content tr:nth-child(even) td { background-color: #f6f8fb; }

        .content blockquote { margin: 3mm 0 4mm 0; padding: 3mm 4mm; background-color: #f2f5fa; border-left: 2.5px solid #0c51d9; color: #1f2937; }
        .content blockquote p:last-child { margin-bottom: 0; }

        /* ---------- Closing ---------- */
        .closing { margin-top: 10mm; page-break-inside: avoid; }
        .closing .lead { margin: 0; }
        .closing .company { margin: 0 0 1mm 0; font-weight: bold; color: #0b1d51; }
        .closing .signatory { margin: 0; font-weight: bold; color: #1f2937; border-top: 1px solid #1f2937; padding-top: 1.5mm; display: inline-block; min-width: 60mm; }
        .closing .role { margin: 0; color: #475569; font-size: 9.5pt; }

        .contact { margin-top: 8mm; border-top: 1px solid #d7dce5; padding-top: 3mm; font-size: 8.5pt; color: #64748b; line-height: 1.6; }
        .contact strong { color: #0b1d51; }
    </style>
</head>
<body>
    {{-- ================= Cover ================= --}}
    <div class="cover">
        <table class="cover-brand">
            <tr>
                <td style="width: 15mm;"><img src="{{ public_path('images/jcd-logo-pdf.png') }}"></td>
                <td>
                    <div class="company">PT JENDELA CAKRA DIGITAL</div>
                    <div class="tagline">Software House &amp; Digital Agency</div>
                </td>
            </tr>
        </table>

        <div class="cover-eyebrow">PROPOSAL PENAWARAN</div>
        <div class="cover-rule"></div>
        <h1 class="cover-title">{{ $proposal->title }}</h1>

        <p class="cover-label">DISIAPKAN UNTUK</p>
        <p class="cover-client">{{ $proposal->client_name }}</p>

        @if($coverPath)
            <div class="cover-image"><img src="{{ $coverPath }}"></div>
        @endif

        <table class="cover-meta">
            <tr>
                <td>
                    <p class="k">NOMOR SURAT</p>
                    <p class="v">{{ $proposal->letter_number ?: '-' }}</p>
                </td>
                <td>
                    <p class="k">TANGGAL</p>
                    <p class="v">{{ $date }}</p>
                </td>
                <td>
                    <p class="k">DISIAPKAN OLEH</p>
                    <p class="v">PT Jendela Cakra Digital</p>
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= Table of contents ================= --}}
    @if(count($toc) > 1)
        <div class="toc">
            <p class="toc-title">DAFTAR ISI</p>
            <table>
                @foreach($toc as [$num, $title])
                    <tr><td class="num">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $title }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- ================= Sections ================= --}}
    @php $number = 0; @endphp
    @foreach($sections as $index => $section)
        @php $hasTitle = trim($section['title'] ?? '') !== ''; @endphp
        {{-- The first section always starts on a fresh page when there's no
             contents list in front of it; otherwise it follows the list. --}}
        <div class="section {{ ($section['page_break_before'] ?? false) ? 'section-break' : '' }}" @if($index === 0 && count($toc) <= 1) style="margin-top: 0;" @endif>
            @php
                // A heading is kept on the same page as the start of its
                // content: the heading and the section's first block sit in
                // one unbreakable wrapper (dompdf ignores page-break-after:
                // avoid on its own), and the rest flows normally after it.
                [$firstBlock, $rest] = $splitFirstBlock($section['content'] ?? '');
            @endphp
            @if($hasTitle)
                @php $number++; @endphp
                <div style="page-break-inside: avoid;">
                    <table class="section-head">
                        <tr>
                            <td class="num">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="title">{{ $section['title'] }}</td>
                        </tr>
                    </table>
                    <div class="content">{!! $firstBlock !!}</div>
                </div>
                <div class="content">{!! $rest !!}</div>
            @else
                <div class="content">{!! $section['content'] ?? '' !!}</div>
            @endif
        </div>
    @endforeach

    {{-- ================= Closing ================= --}}
    <div class="closing">
        <p class="lead">Hormat kami,</p>
        <p class="company">PT Jendela Cakra Digital</p>
        @include('pdf.partials.signature', ['name' => $proposal->signatory_name, 'enabled' => $proposal->use_signature, 'space' => 20])
        @if($proposal->signatory_name)
            <p class="signatory">{{ $proposal->signatory_name }}</p>
        @endif
        @if($proposal->signatory_title)
            <p class="role">{{ $proposal->signatory_title }}</p>
        @endif

        <div class="contact">
            <strong>PT Jendela Cakra Digital</strong><br>
            Jl. Pd. Cabe Raya No.7, Pamulang, Tangerang Selatan, Banten 15418<br>
            contact@jcdigital.co.id &nbsp;·&nbsp; WhatsApp 0878 8279 2511 &nbsp;·&nbsp; jcdigital.co.id
        </div>
    </div>
</body>
</html>
