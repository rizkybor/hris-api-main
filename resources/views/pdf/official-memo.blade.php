<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* @page margin (not .page's own padding) is what dompdf reliably
           repeats as clearance from the fixed letterhead image on every
           page a memo spans, not just the first -- but a fixed element
           needs a negative offset equal to the margin to still reach the
           physical page edge from within the new, inset content box. */
        @page { margin: 48mm 25mm 12mm 25mm; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 10.5pt; }
        .letterhead { position: fixed; top: -48mm; left: -25mm; width: 210mm; height: 297mm; z-index: -1; }
        /* Unlike letter.blade.php / purchase-order.blade.php, this template
           doesn't pull in the shared style-letterhead partial (which sets
           its own @page{margin:0} before this file's @page override) --
           measured via pdftotext -bbox, page 1 here already lines up with
           page 2 with no extra offset, so no margin-top correction is
           needed (adding one, as in those two files, overcorrects). */
        .page { position: relative; z-index: 1; }

        .title {
            text-align: center; font-size: 15.5pt; font-weight: bold; color: #0b1d51;
            letter-spacing: 1px; margin: 0 0 5mm 0; padding-bottom: 3mm;
            border-bottom: 1.5px solid #0b1d51;
        }

        table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        table.info-table td { border: none; padding: 1.5px 0; font-size: 10.5pt; vertical-align: top; }
        table.info-table td.label { width: 20%; font-weight: bold; }
        table.info-table td.colon { width: 3%; }
        table.info-table td.value { width: 77%; }

        .body-content { margin-bottom: 8mm; line-height: 1.6; text-align: justify; }
        .body-content p { margin: 0 0 3mm 0; }
        .body-content table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .body-content table td, .body-content table th { border: 1px solid #94a3b8; padding: 4px 6px; font-size: 10pt; }
        .body-content table th { background-color: #eef2ff; font-weight: bold; }
        .body-content ul, .body-content ol { margin: 0 0 3mm 0; padding-left: 18px; }
        .body-content blockquote { margin: 3mm 0; padding-left: 8px; border-left: 3px solid #cbd5e1; color: #475569; }
        /* Matches the Rich Text Editor's own Title/Subtitle/Heading/Sub
           Heading style menu so the PDF output is WYSIWYG. */
        .body-content h1 { font-size: 17pt; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h2 { font-size: 12.5pt; font-weight: normal; color: #6b7280; margin: -2mm 0 3mm 0; }
        .body-content h3 { font-size: 12.5pt; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h4 { font-size: 11.5pt; font-weight: bold; color: #374151; margin: 0 0 3mm 0; }

        .signature-block { margin-top: 10mm; width: 60mm; page-break-inside: avoid; }
        .signature-block p { margin: 0; line-height: 1.5; }
        .signature-name { font-weight: bold; text-decoration: underline; }

        .rejection-box {
            margin-top: 8mm; padding: 4mm; border: 1px solid #fca5a5; background-color: #fef2f2;
            border-radius: 4px;
        }
        .rejection-box p { margin: 0; font-size: 10pt; color: #991b1b; }
        .rejection-box .rejection-label { font-weight: bold; margin-bottom: 1mm; }

        /* Lampiran (attachments) */
        .attachment-list { margin-top: 8mm; page-break-inside: avoid; }
        .attachment-list .heading { font-weight: bold; margin: 0 0 1.5mm 0; }
        .attachment-list table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .attachment-list td { padding: 1mm 0; vertical-align: top; }
        .attachment-list .no { width: 7mm; }
        .attachment-list .note { color: #64748b; }
        .attachment-page { page-break-before: always; text-align: center; }
        .attachment-caption { text-align: left; font-size: 9.5pt; margin: 0 0 3mm 0; padding-bottom: 1.5mm; border-bottom: 1px solid #cbd5e1; }
        .attachment-caption .muted { color: #64748b; }
        /* Thin frame so a white attachment page stands apart from the paper. */
        .attachment-image { border: 1px solid #cbd5e1; margin-top: 2mm; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-letter-secondary.png') }}">

    <div class="page">
        <p class="title">NOTA DINAS</p>

        <table class="info-table">
            <tr>
                <td class="label">Nomor</td>
                <td class="colon">:</td>
                <td class="value">{{ $documentLetter->document_number }}</td>
            </tr>
            <tr>
                <td class="label">Kepada</td>
                <td class="colon">:</td>
                <td class="value">{{ $recipientLine }}</td>
            </tr>
            <tr>
                <td class="label">Dari</td>
                <td class="colon">:</td>
                <td class="value">{{ $senderLine }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td class="colon">:</td>
                <td class="value">{{ $documentLetter->document_date->locale('id')->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td class="label">Perihal</td>
                <td class="colon">:</td>
                <td class="value">{{ $documentLetter->subject }}</td>
            </tr>
        </table>

        <div class="body-content">
            {!! $documentLetter->body !!}
        </div>

        <div class="signature-block">
            <p>Tangerang Selatan, {{ $documentLetter->document_date->locale('id')->translatedFormat('d F Y') }}</p>
            <p>{{ $senderTitle }},</p>
            @include('pdf.partials.signature', ['name' => $senderName, 'enabled' => $documentLetter->use_signature, 'space' => 18])
            <p class="signature-name">{{ $senderName }}</p>
        </div>

        @if(! empty($lampiran['list']))
            <div class="attachment-list">
                <p class="heading">Lampiran:</p>
                <table>
                    @foreach($lampiran['list'] as $item)
                        <tr>
                            <td class="no">{{ $item['number'] }}.</td>
                            <td>
                                {{ $item['name'] }}
                                @if($item['attached'])
                                    <span class="note">({{ $item['pages'] > 1 ? $item['pages'].' halaman, ' : '' }}terlampir{{ $item['truncated'] ? ' sebagian' : '' }})</span>
                                @else
                                    <span class="note">(tidak dapat ditampilkan di dokumen ini; tersedia di sistem)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if($documentLetter->status === 'rejected' && $documentLetter->rejection_reason)
            <div class="rejection-box">
                <p class="rejection-label">Ditolak oleh {{ $documentLetter->approver?->name ?? 'Finance Manager' }}</p>
                <p>{{ $documentLetter->rejection_reason }}</p>
            </div>
        @endif
    </div>

    {{-- One page per attachment image / PDF page, after the memo itself. --}}
    @foreach($lampiran['pages'] ?? [] as $page)
        <div class="attachment-page">
            <p class="attachment-caption">
                <strong>Lampiran {{ $page['number'] }}</strong> — {{ $page['name'] }}
                @if($page['pages'] > 1)
                    <span class="muted"> · Halaman {{ $page['page'] }} dari {{ $page['pages'] }}</span>
                @endif
            </p>
            <img class="attachment-image" src="{{ $page['path'] }}" style="width: {{ $page['width_mm'] }}mm; height: {{ $page['height_mm'] }}mm;">
        </div>
    @endforeach
</body>
</html>
