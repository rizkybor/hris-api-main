<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.style-letterhead')
    <style>
        /* Override for the BAST-specific letterhead: centered company
           block (vs. right-aligned on other letters). The rotated side-stripe
           tagline itself now lives in the shared style-letterhead partial. */
        .letterhead-center-bast {
            text-align: center;
            margin-bottom: 8mm;
        }

        /* style-letterhead.blade.php's shared .page padding is asymmetric
           (26mm left, 18mm right), so the whole content box -- not just
           centered text like the title/company block above, every
           paragraph and table too -- sits shifted right of the physical
           page center. Overridden here with the average of the two so
           total content width is unchanged, just actually centered. */
        .page { padding-left: 22mm; padding-right: 22mm; }

        .body-content table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .body-content table td, .body-content table th { border: 1px solid #94a3b8; padding: 4px 6px; }
        .body-content table th { background-color: #eef2ff; font-weight: bold; }
        .body-content ul, .body-content ol { margin: 0 0 3mm 0; padding-left: 18px; }
        .body-content blockquote { margin: 3mm 0; padding-left: 8px; border-left: 3px solid #cbd5e1; color: #475569; }
        /* Matches the Rich Text Editor's own Title/Subtitle/Heading/Sub
           Heading style menu so the PDF output is WYSIWYG. */
        .body-content h1 { font-size: 18px; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h2 { font-size: 13px; font-weight: normal; color: #6b7280; margin: -2mm 0 3mm 0; }
        .body-content h3 { font-size: 13px; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h4 { font-size: 12px; font-weight: bold; color: #374151; margin: 0 0 3mm 0; }
    </style>
</head>
<body>
    <div class="side-stripe"></div>
    <div class="side-stripe-label">TRANSFORMING IDEAS INTO INTELLIGENT DIGITAL SOLUTIONS</div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">
    <div class="footer">+62 878 8279 2511 | contact@jcdigital.co.id | www.jcdigital.co.id</div>
    @if($letter->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <div class="letterhead-center-bast">
            <p class="company-name">PT. JENDELA CAKRA DIGITAL</p>
            <p class="company-address">Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang, Kota Tangerang Selatan, Banten 15418</p>
        </div>

        <p style="text-align: center; font-weight: bold; font-size: 13px; margin: 0 0 1mm 0;">BERITA ACARA SERAH TERIMA</p>
        <p style="text-align: center; margin: 0 0 8mm 0;">Nomor: {{ $letter->letter_number }}</p>

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

        <table>
            <tr>
                <td style="border: none; width: 55%;"></td>
                <td style="border: none; width: 45%; text-align: center;">
                    <p style="margin: 0;">Tangerang Selatan, {{ $letter->date->locale('id')->translatedFormat('d F Y') }}</p>
                    <p style="margin: 0;">Direktur Utama,</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; font-weight: bold;">{{ $letter->signatory_name ?? '________________________' }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
