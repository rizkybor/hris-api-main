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
        .doc-title { font-size: 28px; font-weight: bold; color: #0b1d51; text-align: right; margin: 0; }
        .label { color: #0b1d51; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 1px 0; font-size: 10.5px; }
        .items-table th { border-bottom: 1.5px solid #0b1d51; color: #0b1d51; text-align: left; padding: 6px 4px; font-size: 10.5px; }
        .items-table td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; font-size: 10.5px; }
        .totals-table td { border: none; padding: 2px 0; font-size: 10.5px; }
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
    @if($invoice->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        <table class="header-row">
            <tr>
                <td style="width: 50%;"><p class="brand">JENDELA CAKRA<br>DIGITAL</p></td>
                <td style="width: 50%;"><p class="doc-title">INVOICE</p></td>
            </tr>
        </table>

        <table style="margin-top: 6mm;">
            <tr>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Bill To:</p>
                    <p style="margin: 0;">{{ $invoice->client_name }}</p>
                    @if($invoice->client_pic)<p style="margin: 0;">{{ $invoice->client_pic }}</p>@endif
                </td>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <table class="info-table">
                        <tr><td style="width: 45%;" class="label">Invoice Number</td><td>{{ $invoice->invoice_number }}</td></tr>
                        <tr><td class="label">Invoice Date</td><td>{{ strtoupper($invoice->date->translatedFormat('d F Y')) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 6mm;">
            <tr>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Contact information</p>
                    <table class="info-table">
                        <tr><td style="width: 30%;">Email</td><td>{{ $invoice->client_email ?? '-' }}</td></tr>
                        <tr><td>Phone</td><td>{{ $invoice->client_phone ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Payment information</p>
                    <table class="info-table">
                        <tr><td style="width: 40%;">Bank Name:</td><td>{{ $invoice->bank_name ?? '-' }}</td></tr>
                        <tr><td>Account Number:</td><td>{{ $invoice->bank_account ?? '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table" style="margin-top: 8mm;">
            <thead>
                <tr>
                    <th style="width: 46%;">Item Description</th>
                    <th style="width: 18%;">Quantity</th>
                    <th style="width: 18%;">Rate</th>
                    <th style="width: 18%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $item['quantity'] ?? '-' }}</td>
                        <td>{{ $item['rate'] ?? '-' }}</td>
                        <td style="text-align: right;">Rp. {{ number_format($item['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table" style="margin-top: 12mm;">
            <tr>
                <td style="width: 70%;"></td>
                <td style="width: 15%; text-align: right;">Subtotal:</td>
                <td style="width: 15%; text-align: right;">Rp. {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td></td>
                <td style="text-align: right;">Ppn ({{ rtrim(rtrim(number_format($invoice->ppn_percentage, 2), '0'), '.') }}%):</td>
                <td style="text-align: right;">{{ $invoice->ppn_amount > 0 ? 'Rp. '.number_format($invoice->ppn_amount, 0, ',', '.') : 'Rp. -' }}</td>
            </tr>
            <tr>
                <td></td>
                <td style="text-align: right;">Admin Fee:</td>
                <td style="text-align: right;">{{ $invoice->admin_fee > 0 ? 'Rp. '.number_format($invoice->admin_fee, 0, ',', '.') : 'Rp. -' }}</td>
            </tr>
        </table>

        <table style="margin-top: 3mm; border-top: 1.5px solid #0b1d51; border-bottom: 1.5px solid #0b1d51;">
            <tr>
                <td style="border: none; padding: 5px 0; text-align: right; font-weight: bold; width: 70%;">Total Amount Due:</td>
                <td style="border: none; padding: 5px 0; text-align: right; font-weight: bold; width: 30%;">Rp. {{ number_format($invoice->total, 0, ',', '.') }}</td>
            </tr>
        </table>

        @if($invoice->terms)
            <div style="margin-top: 8mm;">
                <p class="label" style="margin: 0 0 2px 0;">Terms and Conditions</p>
                <p style="margin: 0; white-space: pre-line;">{{ $invoice->terms }}</p>
            </div>
        @endif
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
