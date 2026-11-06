<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* @page margin (not .page's own padding) is what dompdf reliably
           repeats as clearance from the fixed letterhead image on every
           page an invoice spans, not just the first -- but a fixed element
           needs a negative offset equal to the margin to still reach the
           physical page edge from within the new, inset content box. */
        /* Right margin lines the content up with the INVOICE title in the
           letterhead image (which sits closer to the edge than 15mm). */
        @page { margin: 42mm 12.5mm 30mm 15mm; }
        body { font-family: "Helvetica", "Arial", sans-serif; color: #1e293b; margin: 0; font-size: 10.5pt; }
        /* Top/bottom bars, brand name, doc title, watermark, and footer are
           all baked into this flattened letterhead image (matching the
           design team's template-invoice.png reference) rather than
           redrawn with CSS. Explicit A4 mm dimensions keep it sized to the
           page regardless of the image's own intrinsic pixel size. */
        .letterhead { position: fixed; top: -42mm; left: -15mm; width: 210mm; height: 297mm; z-index: -1; }
        .page { position: relative; z-index: 1; }
        .label { color: #0b1d51; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 1px 0; font-size: 10pt; vertical-align: top; }
        /* label | ":" | value, value right-aligned: one label width per side
           so the colons line up down each column. */
        .info-table td.k { width: 32%; white-space: nowrap; }
        .info-table td.sep { width: 3%; text-align: center; }
        .info-table td.val { text-align: right; }
        /* Gap between the left-hand values and the right-hand block. */
        .half-left { padding-right: 15mm; }
        .items-table th { border-bottom: 1.5px solid #0b1d51; color: #0b1d51; text-align: left; padding: 6px 4px; font-size: 10pt; }
        .items-table td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; font-size: 10pt; }
        /* Amounts: "Rp." in its own column so it lines up on every row, the
           number right-aligned next to it -- same column widths in the items
           and totals tables. */
        .totals-table td { border: none; padding: 3px 0; font-size: 10pt; }
        .totals-table .t-label { text-align: right; padding-right: 4mm; }
        .items-table .qty { text-align: center; }
        .cur { text-align: left; white-space: nowrap; }
        .amt { text-align: right; white-space: nowrap; }
        .totals-table .grand td { border-top: 1.5px solid #0b1d51; border-bottom: 1.5px solid #0b1d51; padding: 6px 0; font-weight: bold; font-size: 11pt; }
        .totals-table .grand td.t-label { padding-right: 4mm; }
        .terms-box { margin-top: 8mm; padding: 3mm 4mm; border: 0.75px solid #d1d5db; border-radius: 1.5mm; background: #f9fafb; font-size: 9.5pt; }
        .footer { position: fixed; bottom: 7mm; left: 15mm; right: 15mm; font-size: 9pt; color: #0b1d51; }
        .cancelled-stamp {
            position: fixed; top: 78mm; left: 25mm; width: 130mm; text-align: center;
            font-size: 42px; font-weight: bold; color: #dc2626; opacity: 0.35;
            transform: rotate(-25deg); border: 6px solid #dc2626; padding: 6px 0;
        }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-invoice.png') }}">
    @include('pdf.partials.contact-footer', ['marginTop' => 42, 'marginLeft' => 15])
    @if($invoice->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    @php
        $amount = fn ($v) => (float) $v > 0 ? number_format((float) $v, 0, ',', '.') : '-';
        // Rate is free text on the form. A plain rupiah figure ("Rp. 10.000.000",
        // "10000000", "Rp 1.500.000,00") is reformatted into the Rp./amount
        // columns like Total; anything else ("500.000 / bulan") prints as typed.
        $rateNumber = function ($rate) {
            $v = trim(preg_replace('/^rp\.?\s*/i', '', trim((string) $rate)));
            if (! preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$|^\d+(,\d+)?$/', $v)) {
                return null;
            }

            return number_format((float) str_replace(['.', ','], ['', '.'], $v), 0, ',', '.');
        };
    @endphp
    <div class="page">
        <table style="margin-top: 0;">
            <tr>
                <td class="half-left" style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Bill To:</p>
                    <p style="margin: 0;">{{ $invoice->client_name }}</p>
                    @if($invoice->client_pic)<p style="margin: 0;">{{ $invoice->client_pic }}</p>@endif
                </td>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <table class="info-table">
                        <tr><td class="label k">Invoice Number</td><td class="label sep">:</td><td class="val">{{ $invoice->invoice_number }}</td></tr>
                        <tr><td class="label k">Invoice Date</td><td class="label sep">:</td><td class="val">{{ strtoupper($invoice->date->translatedFormat('d F Y')) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 6mm;">
            <tr>
                <td class="half-left" style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Contact information</p>
                    <table class="info-table">
                        <tr><td class="k">Email</td><td class="sep">:</td><td class="val">{{ $invoice->client_email ?? '-' }}</td></tr>
                        <tr><td class="k">Phone</td><td class="sep">:</td><td class="val">{{ $invoice->client_phone ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; border: none; vertical-align: top;">
                    <p class="label" style="margin: 0 0 2px 0;">Payment information</p>
                    <table class="info-table">
                        <tr><td class="k">Bank Name</td><td class="sep">:</td><td class="val">{{ $invoice->bank_name ?? '-' }}</td></tr>
                        <tr><td class="k">Account Number</td><td class="sep">:</td><td class="val">
                            {{-- "1234567890 a.n. PT X" -> number, then the account holder on its own line --}}
                            @php([$accNo, $accName] = array_pad(preg_split('/\s+(?=(?:a\.?\s?n\.?|a\/n)\s)/i', trim((string) $invoice->bank_account), 2), 2, null))
                            {{ $accNo !== '' ? $accNo : '-' }}@if($accName)<br>{{ $accName }}@endif
                        </td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table" style="margin-top: 8mm;">
            <thead>
                <tr>
                    <th style="width: 48%;">Item Description</th>
                    <th style="width: 12%; text-align: center;">Quantity</th>
                    <th colspan="2" style="width: 18%; text-align: right;">Rate</th>
                    {{-- spacer so the Rate and Total money columns don't read as one --}}
                    <th style="width: 4%;"></th>
                    <th colspan="2" style="width: 18%; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    @php($rate = $rateNumber($item['rate'] ?? null))
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td class="qty">{{ filled($item['quantity'] ?? null) ? $item['quantity'] : '-' }}</td>
                        @if($rate !== null)
                            <td class="cur" style="width: 4%;">Rp.</td>
                            <td class="amt" style="width: 14%;">{{ $rate }}</td>
                        @else
                            <td colspan="2" style="text-align: right;">{{ filled($item['rate'] ?? null) ? $item['rate'] : '-' }}</td>
                        @endif
                        <td style="width: 4%;"></td>
                        <td class="cur" style="width: 4%;">Rp.</td>
                        <td class="amt" style="width: 14%;">{{ number_format($item['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table" style="margin-top: 8mm;">
            <tr>
                <td style="width: 46%;"></td>
                <td class="t-label" style="width: 36%;">Subtotal</td>
                <td class="cur" style="width: 4%;">Rp.</td>
                <td class="amt" style="width: 14%;">{{ $amount($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="t-label">PPN ({{ rtrim(rtrim(number_format($invoice->ppn_percentage, 2), '0'), '.') }}%)</td>
                <td class="cur">Rp.</td>
                <td class="amt">{{ $amount($invoice->ppn_amount) }}</td>
            </tr>
            <tr>
                <td></td>
                <td class="t-label">Admin Fee</td>
                <td class="cur">Rp.</td>
                <td class="amt">{{ $amount($invoice->admin_fee) }}</td>
            </tr>
            @if($invoice->icann_fee > 0)
                <tr>
                    <td></td>
                    <td class="t-label">ICANN Fee</td>
                    <td class="cur">Rp.</td>
                    <td class="amt">{{ $amount($invoice->icann_fee) }}</td>
                </tr>
            @endif
            <tr><td colspan="4" style="padding: 1.5mm 0;"></td></tr>
            <tr class="grand">
                <td></td>
                <td class="t-label">Total Amount Due</td>
                <td class="cur">Rp.</td>
                <td class="amt">{{ number_format($invoice->total, 0, ',', '.') }}</td>
            </tr>
        </table>

        @if($invoice->pph23_type)
            <p style="margin: 3mm 0 0 0; font-style: italic;">
                Note: This invoice is subject to {{ number_format((float) $invoice->pph23_percent, 0) }}% PPh 23 withholding by the client upon payment (est. Rp. {{ number_format($invoice->total * $invoice->pph23_percent / 100, 0, ',', '.') }}). Total Amount Due above is unaffected -- the withheld amount is recorded when payment is received.
            </p>
        @endif

        @if($invoice->terms)
            <div class="terms-box">
                <p class="label" style="margin: 0 0 1.5mm 0;">Terms and Conditions</p>
                <p style="margin: 0; white-space: pre-line;">{{ $invoice->terms }}</p>
            </div>
        @endif
    </div>
</body>
</html>
