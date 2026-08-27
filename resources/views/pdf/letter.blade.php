<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.style-letterhead')
    <style>
        /* The letterhead background (header, side-stripe tagline, watermark,
           footer) is a flattened image now -- picked per-letter from the
           template field -- rather than redrawn with CSS, so it matches the
           design team's reference PDFs pixel-for-pixel. Content padding is
           widened on top to clear the taller header text in that image.
           Explicit A4 mm dimensions (not width/height:100%) keep the fixed
           background sized to the actual page regardless of the image's
           own intrinsic pixel dimensions. */
        .letterhead { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; }
        .page { padding: 48mm 16mm 32mm 26mm; }

        .body-content table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .body-content table td, .body-content table th { border: 1px solid #94a3b8; padding: 4px 6px; }
        .body-content table th { background-color: #eef2ff; font-weight: bold; }
        .body-content ul, .body-content ol { margin: 0 0 3mm 0; padding-left: 18px; }
        .body-content blockquote { margin: 3mm 0; padding-left: 8px; border-left: 3px solid #cbd5e1; color: #475569; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-letter-'.($letter->template ?: 'primary').'.png') }}">
    @if($letter->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <p style="margin: 0 0 6mm 0;">Tangerang Selatan, {{ $letter->date->locale('id')->translatedFormat('d F Y') }}</p>

        <table style="margin-bottom: 6mm;">
            <tr>
                <td style="border: none; width: 22%; padding: 1px 0;">Nomor</td>
                <td style="border: none; width: 3%; padding: 1px 0;">:</td>
                <td style="border: none; padding: 1px 0;">{{ $letter->letter_number }}</td>
            </tr>
            <tr>
                <td style="border: none; padding: 1px 0;">Perihal</td>
                <td style="border: none; padding: 1px 0;">:</td>
                <td style="border: none; padding: 1px 0; font-weight: bold;">{{ $letter->subject }}</td>
            </tr>
        </table>

        @if($letter->recipient)
            <p style="margin: 0 0 8mm 0;">
                Kepada Yth.<br>
                {!! nl2br(e($letter->recipient)) !!}<br>
                di Tempat
            </p>
        @endif

        <div class="body-content" style="text-align: justify; line-height: 1.6; margin-bottom: {{ $letter->items ? '6mm' : '14mm' }};">
            {{-- RichTextEditor bodies are already HTML (wrapped in <p> tags etc.) and render as-is.
                 Older letters saved before the rich text editor was added are plain text, so they
                 still need escaping + nl2br to preserve line breaks without showing raw tags. --}}
            @if(str_contains($letter->body, '<'))
                {!! $letter->body !!}
            @else
                {!! nl2br(e($letter->body)) !!}
            @endif
        </div>

        @if($letter->items)
            @php
                $hasPricing = collect($letter->items)->sum('price') > 0;
            @endphp
            <table class="doc-table" style="margin-bottom: 14mm;">
                <thead>
                    <tr>
                        <th style="width: 6%;">No</th>
                        <th style="{{ $hasPricing ? 'width: 34%;' : 'width: 50%;' }}">Deskripsi</th>
                        <th style="{{ $hasPricing ? 'width: 34%;' : 'width: 32%;' }}">Keterangan</th>
                        <th style="width: 12%;">Qty</th>
                        @if($hasPricing)
                            <th style="width: 14%;">Harga</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($letter->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item['description'] }}</td>
                            <td>{!! nl2br(e($item['specification'] ?? '')) !!}</td>
                            <td>{{ $item['qty'] ?? '-' }}</td>
                            @if($hasPricing)
                                <td>Rp {{ number_format($item['price'] ?? 0, 0, ',', '.') }}</td>
                            @endif
                        </tr>
                    @endforeach
                    @if($hasPricing)
                        <tr>
                            <td colspan="4" style="text-align: right; font-weight: bold; background: #0c51d9; color: #fff;">Total</td>
                            <td style="font-weight: bold; background: #0c51d9; color: #fff;">Rp {{ number_format(collect($letter->items)->sum('price'), 0, ',', '.') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif

        @if($letter->second_party_name)
            <table>
                <tr>
                    <td style="border: none; width: 50%; text-align: center;">
                        <p style="margin: 0;">PIHAK PERTAMA</p>
                        <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
                        <div style="height: 20mm;"></div>
                        <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $letter->signatory_name ?? '________________________' }}</p>
                        <p style="margin: 0;">{{ $letter->signatory_title ?? '' }}</p>
                    </td>
                    <td style="border: none; width: 50%; text-align: center;">
                        <p style="margin: 0;">PIHAK KEDUA</p>
                        <p style="margin: 0; font-weight: bold;">{{ $letter->second_party_name }}</p>
                        <div style="height: 20mm;"></div>
                        <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $letter->second_party_signatory_name ?? '________________________' }}</p>
                        <p style="margin: 0;">{{ $letter->second_party_signatory_title ?? '' }}</p>
                    </td>
                </tr>
            </table>
        @else
            <table>
                <tr>
                    <td style="border: none; width: 55%;"></td>
                    <td style="border: none; width: 45%; text-align: center;">
                        <p style="margin: 0;">Hormat kami,</p>
                        <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
                        <div style="height: 20mm;"></div>
                        <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $letter->signatory_name ?? '________________________' }}</p>
                        <p style="margin: 0;">{{ $letter->signatory_title ?? '' }}</p>
                    </td>
                </tr>
            </table>
        @endif
    </div>
</body>
</html>
