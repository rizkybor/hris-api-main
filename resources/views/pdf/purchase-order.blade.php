<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.style-letterhead')
    <style>
        /* The header, watermark, and footer are baked into this flattened
           letterhead image (matching the design team's
           template-letter-secondary.png reference) rather than redrawn with
           CSS. It's position:fixed, so dompdf repeats it on every page
           this document spans without needing to be re-included per page.
           @page margin (not .page's own padding) is what dompdf reliably
           repeats as clearance from that image on every page, including
           pages produced by content overflowing past one page on its own
           (not just the two page-break-before sections below) -- but a
           fixed element needs a negative offset equal to the margin to
           still reach the physical page edge from within the new, inset
           content box. */
        @page { margin: 48mm 0 35mm 0; }
        .letterhead { position: fixed; top: -48mm; left: 0; width: 210mm; height: 297mm; z-index: -1; }
        /* style-letterhead.blade.php's shared .page padding is asymmetric
           (26mm left, 18mm right), so the whole content box -- not just
           centered text like the title above, every table too -- sits
           shifted right of the physical page center. Overridden here with
           the average of the two so total content width is unchanged, just
           actually centered. */
        .page { position: relative; z-index: 1; padding-left: 22mm; padding-right: 22mm; }
        /* dompdf quirk (measured via pdftotext -bbox on letter.blade.php,
           same DOM shape here): the page where .letterhead is declared gets
           ~25mm more top clearance than @page margin alone accounts for --
           likely the fixed img's own flow box still being counted there.
           A block's own margin-top only ever applies on the page where
           that block starts, so it's the right tool to correct *only* the
           first .page div below (right after .letterhead in the DOM) --
           the second .page (page-break-before) starts on a later page
           where the img was already "consumed" from the flow, so it must
           NOT get this same correction or it would push into its own
           header instead. */
        .page-first { margin-top: -25mm; }
        /* .cancelled-stamp (shared partial) is also position:fixed, so its
           top/left need the same margin-box correction to land in the same
           physical spot as before (was centered on the raw page). */
        .cancelled-stamp { top: 72mm; left: 40mm; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-letter-secondary.png') }}">
    @if($order->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page page-first">
        <div style="text-align: center; margin-bottom: 8mm;">
            <p style="font-size: 16px; font-weight: bold; margin: 0;">PURCHASE ORDER (PO)</p>
            <p style="font-size: 11px; margin: 2px 0 0 0;">{{ $order->title }}</p>
        </div>

        <table style="margin-bottom: 6mm;">
            <tr>
                <td style="width: 55%; vertical-align: top; border: none; padding: 0;">
                    <strong>Kepada : {{ $order->client_name }}</strong><br>
                    {{ $order->client_address }}<br>
                    @if($order->client_phone) Telp : {{ $order->client_phone }}<br> @endif
                    @if($order->client_wa) WA : {{ $order->client_wa }} @endif
                </td>
                <td style="width: 45%; vertical-align: top; border: none; padding: 0;">
                    <table style="border: none;">
                        <tr>
                            <td style="border: none; padding: 1px 0; width: 30%;">No PO</td>
                            <td style="border: none; padding: 1px 0;">: {{ $order->po_number }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 1px 0;">Tanggal</td>
                            <td style="border: none; padding: 1px 0;">: {{ $order->date->translatedFormat('d F Y') }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 1px 0;">Dari</td>
                            <td style="border: none; padding: 1px 0;">: PT. Jendela Cakra Digital</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p class="section-title">A. DETAIL ORDER</p>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 22%;">Deskripsi</th>
                    <th style="width: 38%;">Spesifikasi</th>
                    <th style="width: 12%;">Qty</th>
                    <th style="width: 23%;">Harga</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td>{!! nl2br(e($item['specification'] ?? '')) !!}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td>Rp {{ number_format($item['price'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" style="text-align: right; font-weight: bold; background: #f3f4f6;">Subtotal</td>
                    <td style="font-weight: bold; background: #f3f4f6;">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: right; font-weight: bold; background: #091842; color: #fff;">Total</td>
                    <td style="font-weight: bold; background: #091842; color: #fff;">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="page" style="page-break-before: always; margin-top: -30mm;">
        @if(!empty($order->payment_terms))
            <p class="section-title">C. SKEMA PEMBAYARAN</p>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 25%;">Termin</th>
                        <th style="width: 22%;">Jumlah</th>
                        <th style="width: 45%;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->payment_terms as $index => $term)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $term['termin'] }}</td>
                            <td>{{ isset($term['amount']) && $term['amount'] !== null ? 'Rp '.number_format($term['amount'], 0, ',', '.') : '-' }}</td>
                            <td>{{ $term['description'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="section-title">D. KETENTUAN UMUM</p>
        <ol style="padding-left: 14px; margin: 0;">
            <li style="margin-bottom: 3px;">PO ini sah setelah ditandatangani oleh kedua belah pihak.</li>
            <li style="margin-bottom: 3px;">Klien wajib mengkonfirmasi penerimaan PO maksimal 2x24 jam setelah diterima.</li>
            <li style="margin-bottom: 3px;">Unit yang dikirim wajib sesuai spesifikasi yang tercantum dalam PO ini.</li>
            <li style="margin-bottom: 3px;">Kerusakan yang terjadi selama pengiriman menjadi tanggung jawab klien.</li>
            <li style="margin-bottom: 3px;">Garansi unit: [{{ $order->warranty_months ?? '___' }}] bulan sejak tanggal serah terima.</li>
            <li style="margin-bottom: 3px;">Jika terdapat ketidaksesuaian unit, klien wajib melakukan penggantian dalam waktu [{{ $order->replacement_days ?? '___' }}] hari kerja.</li>
        </ol>

        <table style="margin-top: 20mm; page-break-inside: avoid;">
            <tr>
                <td style="width: 50%; border: none; text-align: center;">
                    <p style="margin: 0;">PIHAK PEMBELI</p>
                    <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $order->buyer_signatory_name ?? '________________________' }}</p>
                    <p style="margin: 0;">{{ $order->buyer_signatory_title ?? '' }}</p>
                </td>
                <td style="width: 50%; border: none; text-align: center;">
                    <p style="margin: 0;">PIHAK KLIEN</p>
                    <p style="margin: 0; font-weight: bold;">{{ $order->client_name }}</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $order->client_signatory_name ?? '________________________' }}</p>
                    <p style="margin: 0;">{{ $order->client_signatory_title ?? '' }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
