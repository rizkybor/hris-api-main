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
    @if($letter->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <table class="letterhead-right">
            <tr>
                <td style="border: none; text-align: right; padding: 0;">
                    <p class="company-name">PT. JENDELA CAKRA DIGITAL</p>
                    <p class="company-address">Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang,<br>Kota Tangerang Selatan, Banten 15418</p>
                </td>
            </tr>
        </table>

        <p style="margin: 0 0 6mm 0;">Tangerang Selatan, {{ $letter->date->translatedFormat('d F Y') }}</p>

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

        <div style="text-align: justify; line-height: 1.6; margin-bottom: {{ $letter->items ? '6mm' : '14mm' }};">
            {!! nl2br(e($letter->body)) !!}
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
