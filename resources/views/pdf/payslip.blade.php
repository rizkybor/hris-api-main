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
        .page { padding: 18mm 15mm 22mm 15mm; }
        .header-row { width: 100%; }
        .header-row td { border: none; vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; color: #0b1d51; margin: 0; }
        .doc-title { font-size: 26px; font-weight: bold; color: #0b1d51; text-align: right; margin: 0; }
        .label { color: #0b1d51; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { border: none; padding: 6px 0; font-size: 11px; vertical-align: top; }
        .breakdown-table td { border: none; padding: 5px 0; font-size: 11px; }
        .breakdown-table .amount { text-align: right; }
        .section-title { color: #0b1d51; font-weight: bold; font-size: 12px; margin: 0 0 4px 0; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; }
        .total-row td { border-top: 1.5px solid #0b1d51; font-weight: bold; padding-top: 8px; }
        .net-box { margin-top: 10mm; background-color: #0b1d51; color: #ffffff; padding: 10px 14px; }
        .net-box td { border: none; color: #ffffff; }
        .footer { position: fixed; bottom: 7mm; left: 15mm; right: 15mm; font-size: 9.5px; color: #0b1d51; }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="bottom-bar"></div>
    <img class="watermark" src="{{ public_path('images/jcd-only-color.png') }}">

    <div class="page">
        <table class="header-row">
            <tr>
                <td style="width: 50%;"><p class="brand">JENDELA CAKRA<br>DIGITAL</p></td>
                <td style="width: 50%;"><p class="doc-title">PAYSLIP</p></td>
            </tr>
        </table>

        <p style="text-align: right; margin: 6mm 0 10mm 0;">Period : &nbsp;&nbsp;{{ $period->translatedFormat('F Y') }}</p>

        <table class="info-table">
            <tr><td style="width: 25%;" class="label">Employee Name</td><td>: {{ $employeeName }}</td></tr>
            <tr><td class="label">Department</td><td>: {{ $department }}</td></tr>
            <tr><td class="label">Payment Date</td><td>: {{ $paymentDate ? $paymentDate->translatedFormat('d F Y') : '-' }}</td></tr>
        </table>

        <table style="margin-top: 12mm;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 6mm;">
                    <p class="section-title">EARNINGS</p>
                    <table class="breakdown-table">
                        <tr><td>Basic Salary</td><td class="amount">Rp {{ number_format($basicSalary, 0, ',', '.') }}</td></tr>
                        @if($attendanceDeduction > 0)
                        <tr><td>Attendance Deduction</td><td class="amount">- Rp {{ number_format($attendanceDeduction, 0, ',', '.') }}</td></tr>
                        @endif
                        <tr class="total-row"><td>Gross Salary</td><td class="amount">Rp {{ number_format($grossSalary, 0, ',', '.') }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 6mm;">
                    <p class="section-title">DEDUCTIONS</p>
                    <table class="breakdown-table">
                        <tr><td>BPJS Kesehatan</td><td class="amount">Rp {{ number_format($bpjsKesehatan, 0, ',', '.') }}</td></tr>
                        <tr><td>BPJS JHT</td><td class="amount">Rp {{ number_format($bpjsJht, 0, ',', '.') }}</td></tr>
                        <tr><td>BPJS JP</td><td class="amount">Rp {{ number_format($bpjsJp, 0, ',', '.') }}</td></tr>
                        <tr><td>PPh 21</td><td class="amount">Rp {{ number_format($pph21, 0, ',', '.') }}</td></tr>
                        <tr class="total-row"><td>Total Deductions</td><td class="amount">Rp {{ number_format($totalDeductions, 0, ',', '.') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="net-box">
            <tr>
                <td>
                    <p style="margin: 0; font-size: 10px; opacity: 0.85;">NET SALARY (TAKE HOME)</p>
                    <p style="margin: 4px 0 0 0; font-size: 20px; font-weight: bold;">Rp {{ number_format($netSalary, 0, ',', '.') }}</p>
                </td>
            </tr>
        </table>

        @if($notes)
            <table class="info-table" style="margin-top: 10mm;">
                <tr><td style="width: 15%;" class="label">Notes</td><td>: {{ $notes }}</td></tr>
            </table>
        @endif

        <p style="margin-top: 12mm; font-size: 9.5px; color: #64748b;">
            This is a computer-generated payslip and does not require a signature.
        </p>
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
