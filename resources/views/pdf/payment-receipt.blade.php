<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 11px; }
        .top-bar { position: fixed; top: 0; left: 0; right: 0; height: 10mm; background-color: #0b1d51; }
        .bottom-bar { position: fixed; bottom: 0; left: 0; right: 0; height: 5mm; background-color: #0b1d51; }
        .watermark { position: fixed; top: 110mm; left: 50mm; width: 110mm; height: 93mm; opacity: 0.08; }
        .page { padding: 18mm 15mm 18mm 15mm; }
        .header-row { width: 100%; }
        .header-row td { border: none; vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; color: #0b1d51; margin: 0; }
        .doc-title { font-size: 26px; font-weight: bold; color: #0b1d51; text-align: right; margin: 0; }
        .label { color: #0b1d51; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 6px 0; font-size: 11px; vertical-align: top; }
        .footer { position: fixed; bottom: 7mm; left: 15mm; right: 15mm; font-size: 9.5px; color: #0b1d51; }
        .cancelled-stamp {
            position: fixed; top: 120mm; left: 40mm; width: 130mm; text-align: center;
            font-size: 42px; font-weight: bold; color: #dc2626; opacity: 0.35;
            transform: rotate(-25deg); border: 6px solid #dc2626; padding: 6px 0;
        }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="bottom-bar"></div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">
    @if($receipt->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <table class="header-row">
            <tr>
                <td style="width: 50%;"><p class="brand">JENDELA CAKRA<br>DIGITAL</p></td>
                <td style="width: 50%;"><p class="doc-title">PAYMENT RECEIPT</p></td>
            </tr>
        </table>

        <p style="text-align: right; margin: 6mm 0 10mm 0;">Date : &nbsp;&nbsp;{{ $receipt->date->translatedFormat('d F Y') }}</p>

        <table class="info-table">
            <tr><td style="width: 20%;" class="label">No.</td><td>: {{ $receipt->receipt_number }}</td></tr>
            <tr><td class="label">Received From</td><td>: {{ $receipt->received_from }}</td></tr>
            <tr><td class="label">Amount</td><td>: {{ strtoupper(\App\Helpers\TerbilangHelper::toRupiah((float) $receipt->amount)) }}</td></tr>
            <tr>
                <td class="label">For Payment of</td>
                <td>: {{ $receipt->for_payment_of }}
                    @if($receipt->invoice)
                        berdasarkan Invoice No. <strong>{{ $receipt->invoice->invoice_number }}</strong>.
                    @endif
                </td>
            </tr>
        </table>

        <table style="margin-top: 14mm; border-top: 1.5px solid #0b1d51; border-bottom: 1.5px solid #0b1d51;">
            <tr>
                <td style="border: none; padding: 8px 0; font-weight: bold;">Amount:</td>
                <td style="border: none; padding: 8px 0; font-weight: bold; text-align: right;">Rp. {{ number_format($receipt->amount, 0, ',', '.') }}</td>
            </tr>
        </table>

        <table class="info-table" style="margin-top: 10mm;">
            <tr><td style="width: 20%;" class="label">Payment Status</td><td>: <strong>{{ $receipt->payment_status === 'paid' ? 'LUNAS (PAID)' : 'SEBAGIAN (PARTIAL)' }}</strong></td></tr>
            <tr><td class="label">Date Receipt</td><td>: {{ $receipt->date->translatedFormat('d F Y') }}</td></tr>
        </table>

        <div style="margin-top: 30mm;">
            <p class="label" style="margin: 0;">Recipient,</p>
            <p style="margin: 0;">PT. Jendela Cakra Digital</p>
            <div style="height: 18mm;"></div>
            <p style="margin: 0;">{{ $receipt->recipient_name ?? '________________________' }}</p>
        </div>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td style="border: none; width: 33%;">contact@jcdigital.co.id</td>
                <td style="border: none; width: 34%; text-align: center;">www.jcdigital.co.id</td>
                <td style="border: none; width: 33%; text-align: right;">Phone +62 878-8279-2511</td>
            </tr>
        </table>
    </div>
</body>
</html>
