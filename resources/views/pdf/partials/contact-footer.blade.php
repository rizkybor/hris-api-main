{{--
    Contact row for letterheads whose image keeps only the two footer rules
    (template-invoice.png, template-payment-receipt.png). Sits in the band
    between those rules -- 275-284mm from the top of the A4 page -- so the
    page's @page margins have to be passed in to cancel them out.
--}}
@php
    // Band 275.2-284.0mm; one 8pt line is ~3.4mm tall, so start it ~2.7mm in.
    // Rules run 10.7mm to 200.6mm across the 210mm page.
    $top = (277.9 - $marginTop).'mm';
    $left = (10.7 - $marginLeft).'mm';
    $icon = fn ($name) => public_path("images/icons/{$name}.svg");
@endphp
<table class="contact-footer" style="position: fixed; top: {{ $top }}; left: {{ $left }}; width: 189.9mm; border-collapse: collapse;">
    <tr>
        <td style="width: 34%; text-align: left;"><img src="{{ $icon('mail') }}">contact@jcdigital.co.id</td>
        <td style="width: 32%; text-align: center;"><img src="{{ $icon('globe') }}">www.jcdigital.co.id</td>
        <td style="width: 34%; text-align: right;"><img src="{{ $icon('phone') }}">+62 878-8279-2511</td>
    </tr>
</table>
<style>
    .contact-footer td { border: none; padding: 0 1mm; line-height: 3.4mm; font-size: 8pt; color: #0b1d51; letter-spacing: 0.2px; white-space: nowrap; }
    /* Baseline-aligned, then nudged down so the icon centers on the 8pt text's cap height. */
    .contact-footer img { width: 2.6mm; height: 2.6mm; vertical-align: baseline; margin-right: 1.5mm; position: relative; top: 0.3mm; }
</style>
