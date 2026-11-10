{{--
    License Certificate (A4 portrait), rendered by
    App\Services\LicenseCertificateService. dompdf: no flexbox / grid, so the
    layout uses tables and absolute / fixed positioning. Page 1 is the
    certificate; "Appendix A" (the product's license terms) follows when the
    product has terms.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $product?->name }} License Certificate · {{ $license->key }}</title>
<style>
    @page { margin: 29mm 17mm 24mm 17mm; }

    * { box-sizing: border-box; }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8.2pt;
        line-height: 1.4;
        color: #16182b;
        margin: 0;
    }
    .serif { font-family: "DejaVu Serif", serif; }
    .mono { font-family: "DejaVu Sans Mono", monospace; }
    .muted { color: #6b6f82; }
    .accent { color: {{ $accent }}; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 0; }

    /* ---------- Page frame, header and footer (every page) ---------- */
    .frame {
        position: fixed;
        top: -21mm; bottom: -16mm; left: -9mm; right: -9mm;
        border: 0.5pt solid #c9cbd6;
    }
    .frame2 {
        position: fixed;
        top: -20mm; bottom: -15mm; left: -8mm; right: -8mm;
        border: 0.25pt solid #e6e7ed;
    }
    .corner { position: fixed; width: 9mm; height: 9mm; border-color: {{ $accent }}; border-style: solid; }
    .corner.tl { top: -21mm; left: -9mm; border-width: 1.6pt 0 0 1.6pt; }
    .corner.tr { top: -21mm; right: -9mm; border-width: 1.6pt 1.6pt 0 0; }
    .corner.bl { bottom: -16mm; left: -9mm; border-width: 0 0 1.6pt 1.6pt; }
    .corner.br { bottom: -16mm; right: -9mm; border-width: 0 1.6pt 1.6pt 0; }

    .header { position: fixed; top: -18mm; left: 0; right: 0; height: 14mm; }
    .header .icon { width: 11mm; height: 11mm; border-radius: 2.5mm; }
    .header .product { font-size: 10.5pt; font-weight: bold; letter-spacing: 0.2pt; }
    .header .kicker { font-size: 6.6pt; letter-spacing: 1.6pt; text-transform: uppercase; color: #6b6f82; }
    .header .logo { height: 9mm; }
    .band { position: fixed; top: -5mm; left: 0; right: 0; height: 3mm; overflow: hidden; }
    .band img { width: 100%; height: 3mm; }
    .band-bottom { top: auto; bottom: -6mm; }

    /* Page numbers are drawn by the service (canvas page_text). */
    .footer { position: fixed; bottom: -13.5mm; left: 0; right: 32mm; height: 9mm; font-size: 6.6pt; color: #6b6f82; }
    .footer strong { color: #16182b; }

    /* ---------- Page 1 ---------- */
    .eyebrow { font-size: 7pt; letter-spacing: 2.4pt; text-transform: uppercase; color: {{ $accent }}; font-weight: bold; }
    h1.title { font-size: 19pt; font-weight: normal; margin: 0.8mm 0 1mm 0; letter-spacing: 0.2pt; }
    .lede { font-size: 8.2pt; color: #4b4f63; margin: 0; }

    /* Security-foil key box: pre-rendered pearl / silver foil
       (cert-foil*.png: faint cool shimmer, soft sheens, fine lines,
       micro-text, thin grey border), a round platinum seal
       (cert-seal*.png, 27 mm) with the product icon on its centre
       disc, and an embossed key (a white copy 0.3 mm down / right under
       the dark one). Everything is absolutely positioned for dompdf. */
    .holo { position: relative; margin-top: 5mm; height: 45.7mm; }
    .holo .foil { position: absolute; top: 0; left: 0; width: 176mm; height: 45.7mm; }
    .holo .content { position: absolute; top: 6.8mm; left: 0; width: 176mm; text-align: center; }
    .holo .label { font-size: 6.6pt; letter-spacing: 3pt; text-transform: uppercase; color: #4b4f63; font-weight: bold; }
    .holo .key-wrap { position: relative; height: 10.5mm; margin: 1.6mm 0 2.4mm 0; }
    .holo .key {
        position: absolute; top: 0; left: 0; width: 176mm; text-align: center;
        font-size: 20pt; font-weight: bold; letter-spacing: 2.6pt; color: #14161F;
    }
    .holo .key.emboss { top: 0.3mm; left: 0.3mm; color: #ffffff; }
    .holo .seal { position: absolute; top: -8.5mm; right: -5.5mm; width: 27mm; height: 27mm; }
    .holo .seal-icon { position: absolute; top: 0.75mm; right: 3.75mm; width: 8.5mm; height: 8.5mm; border-radius: 2mm; }
    .holo .meta {
        width: 112mm; margin: 2.6mm auto 0 auto; padding-top: 1.6mm; border-top: 0.3pt solid #9aa0b4;
        font-size: 6.4pt; letter-spacing: 1.4pt; text-transform: uppercase; color: #3d4152;
    }
    .chips { width: auto; margin: 0 auto; }
    .chips td { padding: 0 1mm; vertical-align: middle; }
    .chip-holo { position: relative; width: 34mm; height: 6.1mm; }
    .chip-holo img { position: absolute; top: 0; left: 0; width: 34mm; height: 6.1mm; }
    .chip-holo div {
        position: absolute; top: 0; left: 0; width: 34mm; height: 4.9mm; padding-top: 1.2mm; line-height: 1; text-align: center;
        font-size: 7.2pt; font-weight: bold; letter-spacing: 1.2pt; color: {{ $planInk }};
    }
    .chip {
        display: inline-block; padding: 0.7mm 2.6mm; border-radius: 3mm;
        font-size: 7.2pt; font-weight: bold; letter-spacing: 0.4pt;
    }
    .chip-accent { background: {{ $accent }}; color: #ffffff; }
    .chip-soft { background: #f3f3f7; color: #16182b; border: 0.3pt solid #dcdee6; }

    .section-title {
        font-size: 6.8pt; letter-spacing: 2pt; text-transform: uppercase; font-weight: bold;
        color: #16182b; padding-bottom: 1.4mm; border-bottom: 0.6pt solid {{ $accent }}; margin: 0 0 2.4mm 0;
    }
    .card { border: 0.4pt solid #dcdee6; padding: 3.2mm 3.6mm; }
    .kv td { padding: 0.75mm 0; border-bottom: 0.3pt solid #eeeff3; font-size: 7.9pt; }
    .kv tr:last-child td { border-bottom: none; }
    .kv td.k { width: 30%; color: #6b6f82; padding-right: 2mm; }
    .kv td.v { font-weight: bold; }
    .kv td.v.normal { font-weight: normal; }

    .steps td { padding-right: 3mm; }
    .num {
        width: 5.6mm; height: 4.4mm; padding-top: 1.2mm; border-radius: 2.8mm; background: #16182b; color: #fff;
        font-size: 7.2pt; font-weight: bold; text-align: center; line-height: 1;
    }
    .step-title { font-weight: bold; font-size: 8pt; margin: 1mm 0 0.4mm 0; }
    .step-text { font-size: 7.4pt; color: #4b4f63; }
    ul.notes, ul.rules { margin: 1.4mm 0 0 0; padding-left: 4mm; }
    ul.notes li, ul.rules li { font-size: 7.3pt; color: #4b4f63; margin-bottom: 0.4mm; }

    .subject { margin-top: 3mm; font-size: 7.3pt; color: #4b4f63; border-top: 0.3pt solid #dcdee6; padding-top: 1.6mm; }

    /* ---------- Appendix ---------- */
    .appendix { page-break-before: always; }
    .appendix h2.title { font-size: 16pt; font-weight: normal; margin: 1mm 0 1mm 0; }
    .terms { font-size: 8pt; line-height: 1.5; color: #22253a; }
    .terms h1 { font-family: "DejaVu Serif", serif; font-size: 11.5pt; font-weight: bold; margin: 4mm 0 1.5mm 0; }
    .terms h2 {
        font-size: 8.6pt; font-weight: bold; margin: 4.2mm 0 1.4mm 0; padding-bottom: 0.8mm;
        border-bottom: 0.3pt solid #e3e4ea; color: #16182b;
    }
    .terms h3 { font-size: 8.2pt; font-weight: bold; margin: 3mm 0 1mm 0; }
    .terms p { margin: 0 0 1.6mm 0; text-align: justify; }
    .terms ul, .terms ol { margin: 0 0 2mm 0; padding-left: 5mm; }
    .terms li { margin-bottom: 0.8mm; }
    .terms strong { color: #16182b; }
    .terms a { color: #16182b; text-decoration: none; }
    .terms hr { border: none; border-top: 0.3pt solid #dcdee6; margin: 3mm 0; }

    .ack { margin-top: 6mm; border: 0.6pt solid #16182b; padding: 1.2mm; page-break-inside: avoid; }
    .ack-inner { border: 0.4pt solid {{ $accent }}; padding: 4mm; }
    .ack .line { font-size: 8.2pt; }
</style>
</head>
<body>

{{-- Frame, header and footer repeat on every page --}}
<div class="frame"></div><div class="frame2"></div>
<div class="corner tl"></div><div class="corner tr"></div><div class="corner bl"></div><div class="corner br"></div>

<div class="header">
    <table>
        <tr>
            @if ($productIcon)
                <td style="width: 13mm;"><img class="icon" src="{{ $productIcon }}" alt=""></td>
            @endif
            <td style="vertical-align: middle;">
                <div class="product">{{ $product?->name }}</div>
                <div class="kicker">Software License Certificate</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                @if ($companyLogo)
                    <img class="logo" src="{{ $companyLogo }}" alt="Jendela Cakra Digital">
                @endif
            </td>
        </tr>
    </table>
</div>
@if ($band)
    <div class="band"><img src="{{ $band }}" alt=""></div>
    <div class="band band-bottom"><img src="{{ $band }}" alt=""></div>
@endif

<div class="footer">
    <table>
        <tr>
            <td>
                Issued by <strong>Jendela Cakra Digital</strong> &middot; {{ $issuedOn }} &middot; Ref <strong class="mono">{{ $reference }}</strong><br>
                Verify at {{ $verifyDomain }} with reference {{ $reference }}
            </td>
        </tr>
    </table>
</div>

{{-- ======================= Page 1: certificate ======================= --}}
<div class="eyebrow">Certificate of License</div>
<h1 class="title serif">{{ $product?->name }} {{ $license->planLabel() }} License</h1>
<p class="lede">
    This certifies that the licensee named below holds a valid license for {{ $product?->name }},
    issued by Jendela Cakra Digital under the terms set out on this certificate.
</p>

<div class="holo">
    @if ($foil)
        <img class="foil" src="{{ $foil }}" alt="">
    @endif
    <div class="content">
        <div class="label">License Key</div>
        <div class="key-wrap">
            <div class="key mono emboss">{{ $license->key }}</div>
            <div class="key mono">{{ $license->key }}</div>
        </div>
        <table class="chips">
            <tr>
                <td>
                    <div class="chip-holo">
                        @if ($chip)
                            <img src="{{ $chip }}" alt="">
                        @endif
                        <div>{{ strtoupper($license->planLabel()) }}</div>
                    </div>
                </td>
                @if ($showCategory)
                    <td><span class="chip chip-soft">{{ $categoryLabel }}</span></td>
                @endif
            </tr>
        </table>
        <div class="meta">
            Issued {{ $issuedOn }} &nbsp;&middot;&nbsp; Reference <span class="mono" style="letter-spacing: 0.6pt;">{{ $reference }}</span>
        </div>
    </div>
    @if ($seal)
        <img class="seal" src="{{ $seal }}" alt="">
        @if ($productIcon)
            <img class="seal-icon" src="{{ $productIcon }}" alt="">
        @endif
    @endif
</div>

<table style="margin-top: 4.5mm;">
    <tr>
        {{-- Licensee --}}
        <td style="width: 50%; padding-right: 3mm;">
            <div class="card">
                <div class="section-title">{{ $license->category() === 'organization' ? 'Organization' : 'Licensee' }}</div>
                <table class="kv">
                    @if ($license->category() === 'organization')
                        <tr><td class="k">Organization</td><td class="v">{{ $license->licensee_name }}</td></tr>
                        @if ($organizationTypeLabel)
                            <tr><td class="k">Type</td><td class="v normal">{{ $organizationTypeLabel }}</td></tr>
                        @endif
                        <tr><td class="k">Contact</td><td class="v normal">{{ $license->contact_name ?: '—' }}@if ($license->contact_position), {{ $license->contact_position }}@endif</td></tr>
                    @else
                        <tr><td class="k">Name</td><td class="v">{{ $license->licensee_name }}</td></tr>
                    @endif
                    <tr><td class="k">Email</td><td class="v normal">{{ $license->licensee_email ?: '—' }}</td></tr>
                    <tr><td class="k">Phone</td><td class="v normal">{{ $license->phone ?: '—' }}</td></tr>
                    <tr><td class="k">Address</td><td class="v normal">{{ $license->fullAddress() ?: '—' }}</td></tr>
                </table>
            </div>
        </td>
        {{-- License terms --}}
        <td style="width: 50%; padding-left: 3mm;">
            <div class="card">
                <div class="section-title">License Terms</div>
                <table class="kv">
                    <tr><td class="k">Plan</td><td class="v">{{ $license->planLabel() }}@if ($showCategory) <span class="muted" style="font-weight: normal;">&middot; {{ $categoryLabel }}</span>@endif</td></tr>
                    <tr><td class="k">Platform</td><td class="v normal">{{ $license->platformLabel() }}</td></tr>
                    <tr><td class="k">Type</td><td class="v normal">{{ $license->isLifetime() ? 'Lifetime' : 'Term' }}</td></tr>
                    <tr><td class="k">Start</td><td class="v normal">{{ $startsOn }}</td></tr>
                    <tr><td class="k">End</td><td class="v normal">{{ $endsOn ?? 'Lifetime · never expires' }}</td></tr>
                    <tr><td class="k">Duration</td><td class="v normal">{{ $durationLabel }}</td></tr>
                    <tr><td class="k">Devices</td><td class="v normal">Up to {{ $license->max_devices }} {{ \Illuminate\Support\Str::plural('device', $license->max_devices) }} at a time</td></tr>
                    <tr><td class="k">Status</td><td class="v">{{ $statusLabel }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- How to activate --}}
<div style="margin-top: 4.5mm;">
    <div class="section-title">How to activate</div>
    <table class="steps">
        <tr>
            @foreach ($steps['steps'] as $i => [$title, $text])
                <td style="width: 33.3%;">
                    <div class="num">{{ $i + 1 }}</div>
                    <div class="step-title">{{ $title }}</div>
                    <div class="step-text">{{ $text }}</div>
                </td>
            @endforeach
        </tr>
    </table>
    <ul class="notes">
        @foreach ($steps['notes'] as $note)
            <li>{{ $note }}</li>
        @endforeach
    </ul>
</div>

{{-- Short terms of use --}}
<div style="margin-top: 3.5mm;">
    <div class="section-title">Terms of use</div>
    <ul class="rules">
        @if ($license->category() === 'organization')
            <li>This license is for the organization named above and its members, and is not transferable.</li>
        @else
            <li>This license is personal to the licensee named above and is not transferable.</li>
        @endif
        <li>The key may be active on up to {{ $license->max_devices }} {{ \Illuminate\Support\Str::plural('device', $license->max_devices) }} at the same time.</li>
        <li>Keep the key private; do not publish or share it.</li>
        <li>Jendela Cakra Digital may deactivate a key that is misused, shared or obtained improperly.</li>
    </ul>
</div>

@if ($hasTerms)
    <div class="subject">
        Subject to the <strong>{{ $product->name }} License Terms v{{ $termsVersion }}</strong>, attached as Appendix A.
    </div>
@endif

{{-- ======================= Appendix A: terms ======================= --}}
@if ($hasTerms)
    <div class="appendix">
        <div class="eyebrow">Appendix A</div>
        <h2 class="title serif">License Terms and Conditions</h2>
        <p class="lede">
            {{ $product->name }} License Terms v{{ $termsVersion }}@if ($termsEffectiveOn) &middot; effective {{ $termsEffectiveOn }}@endif
            &middot; part of certificate {{ $reference }}
        </p>

        <div class="terms" style="margin-top: 3mm;">
            {!! $termsHtml !!}
        </div>

        <div class="ack">
            <div class="ack-inner">
                <div class="section-title" style="margin-bottom: 2mm;">Acknowledgement</div>
                <div class="line">
                    Licensee: <strong>{{ $license->licensee_name }}</strong>
                    &nbsp;&middot;&nbsp; License key: <strong class="mono">{{ $license->key }}</strong>
                    &nbsp;&middot;&nbsp; Terms version: <strong>{{ $termsVersion }}</strong>
                    &nbsp;&middot;&nbsp; Issued: <strong>{{ $issuedOn }}</strong>
                </div>
            </div>
        </div>
    </div>
@endif

</body>
</html>
