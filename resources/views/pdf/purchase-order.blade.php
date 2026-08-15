<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.style-letterhead')
</head>
<body>
    <div class="side-stripe"></div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">
    <div class="footer">+62 878 8279 2511 | contact@jcdigital.co.id | www.jcdigital.co.id</div>
    @if($order->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <div class="letterhead-center">
            <p class="company-name">PT. JENDELA CAKRA DIGITAL</p>
            <p class="company-address">Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang, Kota<br>Tangerang Selatan, Banten 15418</p>
        </div>

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
                    <td colspan="4" style="text-align: right; font-weight: bold; background: #0c51d9; color: #fff;">Total</td>
                    <td style="font-weight: bold; background: #0c51d9; color: #fff;">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="page" style="page-break-before: always;">
        <div class="letterhead-center">
            <p class="company-name">PT. JENDELA CAKRA DIGITAL</p>
            <p class="company-address">Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang, Kota<br>Tangerang Selatan, Banten 15418</p>
        </div>

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
            <li style="margin-bottom: 3px;">Vendor wajib mengkonfirmasi penerimaan PO maksimal 2x24 jam setelah diterima.</li>
            <li style="margin-bottom: 3px;">Unit yang dikirim wajib sesuai spesifikasi yang tercantum dalam PO ini.</li>
            <li style="margin-bottom: 3px;">Kerusakan yang terjadi selama pengiriman menjadi tanggung jawab vendor.</li>
            <li style="margin-bottom: 3px;">Garansi unit: [{{ $order->warranty_months ?? '___' }}] bulan sejak tanggal serah terima.</li>
            <li style="margin-bottom: 3px;">Jika terdapat ketidaksesuaian unit, vendor wajib melakukan penggantian dalam waktu [{{ $order->replacement_days ?? '___' }}] hari kerja.</li>
        </ol>

        <table style="margin-top: 20mm;">
            <tr>
                <td style="width: 50%; border: none; text-align: center;">
                    <p style="margin: 0;">PIHAK PEMBELI</p>
                    <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $order->buyer_signatory_name ?? '________________________' }}</p>
                    <p style="margin: 0;">{{ $order->buyer_signatory_title ?? '' }}</p>
                </td>
                <td style="width: 50%; border: none; text-align: center;">
                    <p style="margin: 0;">PIHAK VENDOR</p>
                    <p style="margin: 0; font-weight: bold;">{{ $order->client_name }}</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $order->vendor_signatory_name ?? '________________________' }}</p>
                    <p style="margin: 0;">{{ $order->vendor_signatory_title ?? '' }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
