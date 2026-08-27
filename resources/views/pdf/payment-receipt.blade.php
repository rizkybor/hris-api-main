<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 11px; }
        /* Top/bottom bars, brand name, doc title, watermark, and footer are
           all baked into this flattened letterhead image (matching the
           design team's template-payment-receipt.png reference) rather than
           redrawn with CSS. Explicit A4 mm dimensions keep it sized to the
           page regardless of the image's own intrinsic pixel size. */
        .letterhead { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; }
        .page { padding: 42mm 15mm 30mm 15mm; }
        .label { color: #0b1d51; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 6px 0; font-size: 11px; vertical-align: top; }
        .cancelled-stamp {
            position: fixed; top: 120mm; left: 40mm; width: 130mm; text-align: center;
            font-size: 42px; font-weight: bold; color: #dc2626; opacity: 0.35;
            transform: rotate(-25deg); border: 6px solid #dc2626; padding: 6px 0;
        }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-payment-receipt.png') }}">
    @if($receipt->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <p style="text-align: right; margin: 0 0 10mm 0;">Date : &nbsp;&nbsp;{{ $receipt->date->translatedFormat('d F Y') }}</p>

        <table class="info-table">
            <tr><td style="width: 20%;" class="label">No.</td><td>: {{ $receipt->receipt_number }}</td></tr>
            <tr><td class="label">Received From</td><td>: {{ $receipt->received_from }}</td></tr>
            <tr><td class="label">Amount</td><td>: {{ strtoupper(\App\Helpers\TerbilangHelper::toRupiah((float) $receipt->amount)) }}</td></tr>
            @if($receipt->pph23_amount)
                <tr><td class="label">Note</td><td>: Net of {{ number_format((float) $receipt->pph23_percent, 0) }}% PPh 23 withholding (Rp {{ number_format((float) $receipt->pph23_amount, 0, ',', '.') }})</td></tr>
            @endif
            <tr>
                <td class="label">For Payment of</td>
                <td>: {{ $receipt->for_payment_of }}
                    @if($receipt->invoice)
                        berdasarkan Invoice No. <strong>{{ $receipt->invoice->invoice_number }}</strong>.
                    @endif
                </td>
            </tr>
        </table>

        @if($receipt->pph23_amount)
            <table style="margin-top: 14mm;">
                <tr>
                    <td style="border: none; padding: 3px 0;">Gross Amount</td>
                    <td style="border: none; padding: 3px 0; text-align: right;">Rp. {{ number_format((float) $receipt->amount + (float) $receipt->pph23_amount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 3px 0;">PPh 23 Withheld ({{ number_format((float) $receipt->pph23_percent, 0) }}%)</td>
                    <td style="border: none; padding: 3px 0; text-align: right;">- Rp. {{ number_format((float) $receipt->pph23_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        @endif
        <table style="margin-top: {{ $receipt->pph23_amount ? '2mm' : '14mm' }}; border-top: 1.5px solid #0b1d51; border-bottom: 1.5px solid #0b1d51;">
            <tr>
                <td style="border: none; padding: 8px 0; font-weight: bold;">{{ $receipt->pph23_amount ? 'Net Amount Received:' : 'Amount:' }}</td>
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
</body>
</html>
