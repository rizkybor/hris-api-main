<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 11px; }
        .letterhead { position: fixed; top: 0; left: 0; width: 100%; height: 100%; }
        .page { padding: 48mm 16mm 32mm 26mm; position: relative; }

        .title {
            text-align: center; font-size: 16px; font-weight: bold; color: #0b1d51;
            letter-spacing: 1px; margin: 0 0 5mm 0; padding-bottom: 3mm;
            border-bottom: 1.5px solid #0b1d51;
        }

        table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        table.info-table td { border: none; padding: 1.5px 0; font-size: 11px; vertical-align: top; }
        table.info-table td.label { width: 20%; font-weight: bold; }
        table.info-table td.colon { width: 3%; }
        table.info-table td.value { width: 77%; }

        .body-content { margin-bottom: 8mm; line-height: 1.6; text-align: justify; }
        .body-content p { margin: 0 0 3mm 0; }
        .body-content table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .body-content table td, .body-content table th { border: 1px solid #94a3b8; padding: 4px 6px; font-size: 10.5px; }
        .body-content table th { background-color: #eef2ff; font-weight: bold; }
        .body-content ul, .body-content ol { margin: 0 0 3mm 0; padding-left: 18px; }
        .body-content blockquote { margin: 3mm 0; padding-left: 8px; border-left: 3px solid #cbd5e1; color: #475569; }

        .signature-block { margin-top: 10mm; width: 60mm; }
        .signature-block p { margin: 0; line-height: 1.5; }
        .signature-space { height: 18mm; }
        .signature-name { font-weight: bold; text-decoration: underline; }

        .rejection-box {
            margin-top: 8mm; padding: 4mm; border: 1px solid #fca5a5; background-color: #fef2f2;
            border-radius: 4px;
        }
        .rejection-box p { margin: 0; font-size: 10.5px; color: #991b1b; }
        .rejection-box .rejection-label { font-weight: bold; margin-bottom: 1mm; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/official-memo-letterhead.png') }}">

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
            <div class="signature-space"></div>
            <p class="signature-name">{{ $senderName }}</p>
        </div>

        @if($documentLetter->status === 'rejected' && $documentLetter->rejection_reason)
            <div class="rejection-box">
                <p class="rejection-label">Ditolak oleh {{ $documentLetter->approver?->name ?? 'Finance Manager' }}</p>
                <p>{{ $documentLetter->rejection_reason }}</p>
            </div>
        @endif
    </div>
</body>
</html>
