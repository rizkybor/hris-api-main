<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Notulen Rapat - {{ $note->title }}</title>
    @php
        $typeLabel = ['internal' => 'Internal', 'external' => 'Eksternal'][$note->meeting_type] ?? ucfirst((string) $note->meeting_type);
        $internal = $note->attendees->map(fn ($e) => $e->user?->name)->filter()->values();
        $external = collect($note->external_attendees ?? [])->filter(fn ($a) => filled($a['name'] ?? null));
        $actions = collect($note->action_items ?? [])->filter(fn ($a) => filled($a['text'] ?? null))->values();
        $doneCount = $actions->where('done', true)->count();
    @endphp
    <style>
        /* Compact, print-first notulen: built-in Helvetica only (nothing
           embedded), one small logo, solid JCD navy/blue. The footer line
           and page numbers are drawn by MeetingNoteController. */
        @page { margin: 16mm 18mm 20mm 18mm; }

        body { font-family: "Helvetica", "Arial", sans-serif; color: #1f2937; font-size: 10pt; line-height: 1.5; margin: 0; }

        .brand { width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #0b1d51; }
        .brand td { vertical-align: middle; padding: 0 0 3mm 0; }
        .brand img { height: 9mm; }
        .brand .company { font-size: 8.5pt; font-weight: bold; color: #0b1d51; letter-spacing: 1px; }
        .brand .tagline { font-size: 7.5pt; color: #64748b; }
        .brand .doc { text-align: right; }
        .brand .doc-label { font-size: 8pt; font-weight: bold; color: #0c51d9; letter-spacing: 2px; }
        .brand .doc-number { font-size: 8.5pt; color: #334155; }

        h1.title { font-size: 16pt; line-height: 1.25; font-weight: bold; color: #0b1d51; margin: 6mm 0 4mm 0; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        .meta td { padding: 1.4mm 0; vertical-align: top; font-size: 9pt; border-bottom: 1px solid #eef1f5; }
        .meta .k { width: 32mm; color: #64748b; }
        .meta .v { color: #1f2937; }

        .section { margin-top: 5mm; }
        .section-title { font-size: 9pt; font-weight: bold; color: #0c51d9; letter-spacing: 1.5px; margin: 0 0 2mm 0; padding-bottom: 1.2mm; border-bottom: 1px solid #d7dce5; }
        .empty { color: #94a3b8; font-style: italic; font-size: 9pt; margin: 0; }

        .attendees { width: 100%; border-collapse: collapse; }
        .attendees td { width: 50%; vertical-align: top; padding: 0 4mm 0 0; font-size: 9pt; }
        .attendees .label { font-weight: bold; color: #334155; margin: 0 0 1mm 0; }
        .attendees ol { margin: 0; padding-left: 5mm; }
        .attendees li { margin-bottom: 0.6mm; }
        .attendees .org { color: #64748b; }

        .content p { margin: 0 0 2.2mm 0; }
        .content ul, .content ol { margin: 0 0 2.5mm 0; padding-left: 6mm; }
        .content li { margin-bottom: 1mm; }
        .content h1 { font-size: 13pt; font-weight: bold; color: #0b1d51; margin: 4mm 0 1.5mm 0; }
        .content h2 { font-size: 11.5pt; font-weight: bold; color: #0b1d51; margin: 3.5mm 0 1.5mm 0; }
        .content h3, .content h4 { font-size: 10.5pt; font-weight: bold; color: #334155; margin: 3mm 0 1.2mm 0; }
        .content a { color: #0c51d9; }
        .content hr { border: none; border-top: 1px solid #d7dce5; margin: 3mm 0; }
        .content blockquote { margin: 2.5mm 0 3mm 0; padding: 2mm 3mm; background-color: #f2f5fa; border-left: 2px solid #0c51d9; }
        .content blockquote p:last-child { margin-bottom: 0; }
        .content table { width: 100%; border-collapse: collapse; margin: 1.5mm 0 3mm 0; font-size: 9pt; line-height: 1.35; }
        .content th, .content td { border: 1px solid #d7dce5; padding: 1.4mm 2mm; vertical-align: top; text-align: left; }
        .content th { background-color: #f2f5fa; font-weight: bold; color: #0b1d51; }

        .actions { width: 100%; border-collapse: collapse; font-size: 9pt; line-height: 1.35; }
        .actions th { background-color: #0b1d51; color: #ffffff; font-weight: bold; text-align: left; padding: 1.6mm 2mm; }
        .actions td { border-bottom: 1px solid #e5e9f0; padding: 1.6mm 2mm; vertical-align: top; }
        .actions .no { width: 7mm; color: #64748b; }
        .actions th.no { color: #ffffff; }
        .actions .pic { width: 38mm; }
        .actions .status { width: 22mm; }
        .badge { font-size: 7.5pt; font-weight: bold; padding: 0.5mm 1.8mm; border-radius: 1mm; }
        .badge-done { background-color: #dcfce7; color: #166534; }
        .badge-open { background-color: #fef3c7; color: #92400e; }
        .done-text { color: #64748b; }
        .summary { font-size: 8.5pt; color: #64748b; margin: 1.5mm 0 0 0; }

        .files { margin: 0; padding-left: 5mm; font-size: 9pt; }
        .files li { margin-bottom: 0.6mm; }
        .files .size { color: #64748b; }

        .printed { margin-top: 7mm; font-size: 7.5pt; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="brand">
        <tr>
            <td style="width: 12mm;"><img src="{{ public_path('images/jcd-logo-pdf.png') }}"></td>
            <td>
                <div class="company">PT JENDELA CAKRA DIGITAL</div>
                <div class="tagline">Software House &amp; Digital Agency</div>
            </td>
            <td class="doc">
                <div class="doc-label">NOTULEN RAPAT</div>
                <div class="doc-number">{{ $note->document_number ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <h1 class="title">{{ $note->title }}</h1>

    <table class="meta">
        <tr>
            <td class="k">Hari, tanggal</td>
            <td class="v">{{ $meetingDate ? $meetingDate->translatedFormat('l, j F Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="k">Waktu</td>
            <td class="v">{{ $meetingDate ? $meetingDate->format('H:i').' WIB' : '-' }}</td>
        </tr>
        <tr>
            <td class="k">Jenis rapat</td>
            <td class="v">{{ $typeLabel }}</td>
        </tr>
        <tr>
            <td class="k">Notulis</td>
            <td class="v">{{ $note->creator?->name ?? '-' }}</td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">PESERTA RAPAT</p>
        @if($internal->isEmpty() && $external->isEmpty())
            <p class="empty">Peserta tidak dicatat.</p>
        @else
            <table class="attendees">
                <tr>
                    <td>
                        <p class="label">Internal ({{ $internal->count() }})</p>
                        @if($internal->isEmpty())
                            <p class="empty">-</p>
                        @else
                            <ol>
                                @foreach($internal as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                            </ol>
                        @endif
                    </td>
                    <td>
                        <p class="label">Eksternal ({{ $external->count() }})</p>
                        @if($external->isEmpty())
                            <p class="empty">-</p>
                        @else
                            <ol>
                                @foreach($external as $guest)
                                    <li>{{ $guest['name'] }}@if(filled($guest['organization'] ?? null)) <span class="org">- {{ $guest['organization'] }}</span>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    </td>
                </tr>
            </table>
        @endif
    </div>

    <div class="section">
        <p class="section-title">CATATAN RAPAT</p>
        @if($body !== '')
            <div class="content">{!! $body !!}</div>
        @else
            <p class="empty">Tidak ada catatan.</p>
        @endif
    </div>

    <div class="section">
        <p class="section-title">TINDAK LANJUT</p>
        @if($actions->isEmpty())
            <p class="empty">Tidak ada tindak lanjut.</p>
        @else
            <table class="actions">
                <thead>
                    <tr>
                        <th class="no">No</th>
                        <th>Tindakan</th>
                        <th class="pic">PIC</th>
                        <th class="status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $i => $item)
                        <tr>
                            <td class="no">{{ $i + 1 }}</td>
                            <td class="{{ ! empty($item['done']) ? 'done-text' : '' }}">{{ $item['text'] }}</td>
                            <td class="pic">{{ $item['assignee'] ?? '' ?: '-' }}</td>
                            <td class="status">
                                @if(! empty($item['done']))
                                    <span class="badge badge-done">Selesai</span>
                                @else
                                    <span class="badge badge-open">Belum</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="summary">{{ $doneCount }} dari {{ $actions->count() }} tindak lanjut selesai.</p>
        @endif
    </div>

    @if($note->attachments->isNotEmpty())
        <div class="section">
            <p class="section-title">LAMPIRAN</p>
            <ol class="files">
                @foreach($note->attachments as $file)
                    <li>{{ $file->original_name }}@if($file->size_file) <span class="size">({{ $file->size_file }})</span>@endif</li>
                @endforeach
            </ol>
            <p class="summary">Berkas lampiran tersedia di Backoffice pada Meeting Note ini.</p>
        </div>
    @endif

    <p class="printed">
        Dicetak oleh {{ $printedBy }} pada {{ $printedAt->translatedFormat('j F Y, H:i') }} WIB
        @if($note->updater) · terakhir diubah oleh {{ $note->updater->name }} @endif
    </p>
</body>
</html>
