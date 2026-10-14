<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->documentTitle() }}</title>
    <style>
        /* Laid out after the proposals JC Digital used to export from
           Notion: cover image, large title, an info callout, numbered
           sections, light tables, and a title/page-number footer (drawn by
           ProposalController::addFooter). */
        @page { margin: 18mm 20mm 20mm 20mm; }

        body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2937; font-size: 10.5pt; line-height: 1.5; margin: 0; }

        .cover { height: 58mm; overflow: hidden; border-radius: 3mm; margin-bottom: 6mm; }
        .cover img { width: 100%; }

        .doc-title { font-size: 22pt; font-weight: bold; color: #111827; line-height: 1.2; margin: 0 0 5mm 0; }

        .callout { background-color: #eef4fb; border-radius: 2.5mm; padding: 3.5mm 4.5mm; margin-bottom: 7mm; font-size: 9.5pt; line-height: 1.55; color: #1f2937; }
        .callout .brand { font-weight: bold; color: #0c51d9; letter-spacing: 0.2px; }

        .section { margin-top: 6mm; }
        .section-break { page-break-before: always; margin-top: 0; }
        .section-title { font-size: 14pt; font-weight: bold; color: #111827; margin: 0 0 2.5mm 0; }

        .content p { margin: 0 0 2.5mm 0; }
        .content ul, .content ol { margin: 0 0 3mm 0; padding-left: 6mm; }
        .content li { margin-bottom: 1.2mm; }
        .content h1 { font-size: 15pt; font-weight: bold; margin: 4mm 0 2mm 0; }
        .content h2 { font-size: 12.5pt; font-weight: normal; color: #6b7280; margin: 3mm 0 2mm 0; }
        .content h3 { font-size: 11.5pt; font-weight: bold; color: #111827; margin: 4mm 0 1.5mm 0; }
        .content h4 { font-size: 10.5pt; font-weight: bold; color: #374151; margin: 3mm 0 1.5mm 0; }
        .content table { width: 100%; border-collapse: collapse; margin: 2mm 0 3.5mm 0; font-size: 9.5pt; line-height: 1.4; }
        .content th, .content td { border: 1px solid #e5e7eb; padding: 1.6mm 2.2mm; vertical-align: top; text-align: left; }
        .content th { background-color: #f7f7f5; font-weight: bold; color: #374151; }
        .content blockquote { margin: 3mm 0; padding: 3mm 4mm; background-color: #f7f7f5; border-left: 3px solid #0c51d9; color: #374151; }
        .content img { max-width: 100%; margin: 2mm 0; }
        .content hr { border: none; border-top: 1px solid #e5e7eb; margin: 4mm 0; }
        .content a { color: #0c51d9; }

        .closing { margin-top: 6mm; page-break-inside: avoid; }
        .closing .signatory { font-weight: bold; margin: 0; }
        .closing .muted { color: #4b5563; margin: 0; font-size: 9.5pt; }
    </style>
</head>
<body>
    @if($coverPath)
        <div class="cover"><img src="{{ $coverPath }}"></div>
    @endif

    <h1 class="doc-title">{{ $proposal->documentTitle() }}</h1>

    <div class="callout">
        <span class="brand">PT JENDELA CAKRA DIGITAL · Proposal Penawaran</span><br>
        {{ $proposal->title }}<br>
        Untuk: {{ $proposal->client_name }} · Tanggal: {{ $proposal->proposal_date->copy()->locale('id')->translatedFormat('j F Y') }}
        @if($proposal->letter_number)
            <br>Lampiran Surat Nomor: {{ $proposal->letter_number }}
        @endif
    </div>

    @php $number = 0; @endphp
    @foreach($sections as $section)
        @php $hasTitle = trim($section['title'] ?? '') !== ''; @endphp
        <div class="section {{ ($section['page_break_before'] ?? false) ? 'section-break' : '' }}">
            {{-- Untitled sections are free-form blocks and don't take a number. --}}
            @if($hasTitle)
                @php $number++; @endphp
                <h2 class="section-title">{{ $number }}. {{ $section['title'] }}</h2>
            @endif
            <div class="content">{!! $section['content'] ?? '' !!}</div>
        </div>
    @endforeach

    <div class="closing">
        <p style="margin: 0;">Hormat kami,</p>
        <p style="margin: 0 0 1mm 0; font-weight: bold;">PT Jendela Cakra Digital</p>
        @include('pdf.partials.signature', ['name' => $proposal->signatory_name, 'enabled' => $proposal->use_signature, 'space' => 20])
        @if($proposal->signatory_name)
            <p class="signatory">{{ $proposal->signatory_name }}</p>
        @endif
        @if($proposal->signatory_title)
            <p class="muted">{{ $proposal->signatory_title }}</p>
        @endif
        <p class="muted" style="margin-top: 3mm;">PT Jendela Cakra Digital</p>
        <p class="muted">Jl. Pd. Cabe Raya No.7, Pamulang, Tangerang Selatan, Banten 15418</p>
        <p class="muted">Email: contact@jcdigital.co.id · WhatsApp: 6287882792511 · Website: jcdigital.co.id</p>
    </div>
</body>
</html>
