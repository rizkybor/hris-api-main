<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf.partials.style-letterhead')
    @php
        $isSecondaryTemplate = ($letter->template ?: 'primary') === 'secondary';
        $dateLine = 'Tangerang Selatan, '.$letter->date->locale('id')->translatedFormat('d F Y');
        // Single source of truth for the page's top clearance, so it, the
        // .letterhead image, and .cancelled-stamp (both position:fixed,
        // and so keyed off this same margin to stay pinned to their actual
        // physical spot -- see the notes further down) never drift out of
        // sync when this number changes.
        $topMargin = $isSecondaryTemplate ? 45 : 34;
    @endphp
    <style>
        /* The letterhead background (header, side-stripe tagline, watermark,
           footer) is a flattened image now -- picked per-letter from the
           template field -- rather than redrawn with CSS, so it matches the
           design team's reference PDFs pixel-for-pixel. Content padding is
           widened on top to clear the taller header text in that image.
           Explicit A4 mm dimensions (not width/height:100%) keep the fixed
           background sized to the actual page regardless of the image's
           own intrinsic pixel dimensions. */
        /* @page margin (not .page's own padding) defines dompdf's actual
           repeating margin box -- reliable on every page, unlike a plain
           block's padding which only holds on the page where that block
           starts. The earlier attempt at this failed because the
           letterhead image stayed positioned at the physical page's 0,0
           corner (ignoring the new margin box entirely); a fixed element
           needs a negative offset equal to the margin to reach back out
           to the physical page edge from within the now-inset content box. */
        /* $topMargin per template (see the PHP block above) -- raised for
           Secondary specifically because continuation pages (2+) otherwise
           started right at the bare @page margin with nothing pushing the
           CONTENT down, landing the first line of body text almost
           touching the letterhead's tagline. `@page :first` to give page 1
           a different (smaller) margin than the rest was tried first and
           didn't work -- dompdf applied the very first @page rule
           uniformly to every page regardless of a later rule or a :first
           pseudo-class -- so this raises the content margin for every page
           instead. .letterhead and .cancelled-stamp below get their fixed
           top offset computed off this same $topMargin, so the
           header/footer background image itself stays pinned to the
           identical physical spot -- only the flowing content (body text,
           and page 1's own title block, which still adds its own margin
           below this) moves with it. */
        @page { margin: {{ $topMargin }}mm 0 6mm 0; }
        /* dompdf quirk: page 1 renders with ~25mm more top clearance than
           continuation pages, measured via pdftotext -bbox (page 1's first
           line sat at 68.76mm from the top vs page 2's 43.71mm, despite
           identical @page margin) -- likely the fixed .letterhead img's own
           flow box still being counted on the one page where it's declared.
           A plain margin-top on .page only ever applies on the page where
           that block starts (page 1), never repeating on overflow pages,
           so it's the correct tool to correct page 1 alone without
           affecting page 2+. This offset is independent of the @page
           margin value above (the quirk itself doesn't scale with it), so
           it's the same for both templates. */
        /* style-letterhead.blade.php's shared .page padding is asymmetric
           (26mm left, 18mm right), so the whole content box -- not just
           centered elements, EVERY paragraph, table, and signature block --
           sits shifted right of the physical page center. Overridden here
           (not in the shared partial, which purchase-order/official-memo/
           bast also use and weren't asked about) with the average of the
           two so total content width is unchanged, just centered. */
        .page { position: relative; z-index: 1; margin-top: -25mm; padding-left: 22mm; padding-right: 22mm; }
        .letterhead { position: fixed; top: -{{ $topMargin }}mm; left: 0; width: 210mm; height: 297mm; z-index: -1; }
        /* .cancelled-stamp (shared partial) is also position:fixed, so its
           top/left need the same margin-box correction to land in the same
           physical spot as before (was centered on the raw page, at 120mm
           from the top -- so top = 120 - $topMargin keeps it there
           regardless of what $topMargin is). */
        .cancelled-stamp { top: {{ 120 - $topMargin }}mm; left: 40mm; }

        /* Paragraph indentation is an explicit choice made in the editor
           (Tab, or its Align/Indent controls) and preserved as-authored --
           not auto-applied here, so a letter's indentation matches what
           was actually typed instead of forcing every paragraph to indent
           whether the author wanted that or not. */
        .body-content p, .body-content > div { margin: 0 0 3mm 0; }
        .body-content table { width: 100%; border-collapse: collapse; margin: 3mm 0; }
        .body-content table td, .body-content table th { border: 1px solid #94a3b8; padding: 4px 6px; }
        .body-content table th { background-color: #eef2ff; font-weight: bold; }
        .body-content ul, .body-content ol { margin: 0 0 3mm 0; padding-left: 18px; }
        .body-content blockquote { margin: 3mm 0; padding-left: 8px; border-left: 3px solid #cbd5e1; color: #475569; }
        /* Matches the Rich Text Editor's own Title/Subtitle/Heading/Sub
           Heading style menu so the PDF output is WYSIWYG. */
        .body-content h1 { font-size: 18px; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h2 { font-size: 13px; font-weight: normal; color: #6b7280; margin: -2mm 0 3mm 0; }
        .body-content h3 { font-size: 13px; font-weight: bold; margin: 0 0 3mm 0; }
        .body-content h4 { font-size: 12px; font-weight: bold; color: #374151; margin: 0 0 3mm 0; }
    </style>
</head>
<body>
    <img class="letterhead" src="{{ public_path('images/template-letter-'.($letter->template ?: 'primary').'.png') }}">
    @if($letter->status === 'cancelled')
        <div class="cancelled-stamp">DIBATALKAN</div>
    @endif

    <div class="page">
        {{-- Primary keeps the date at the top (its own established layout).
             Secondary instead places it right above the closing signature
             block below, matching a Surat Keterangan's conventional layout. --}}
        @unless($isSecondaryTemplate)
            <p style="margin: 0 0 6mm 0; text-align: right;">{{ $dateLine }}</p>
        @endunless

        @if($isSecondaryTemplate)
            {{-- The Secondary letterhead's logo/header is center-aligned
                 (unlike Primary's left-aligned one), so the document title
                 (from the Letter Code) plus Nomor/Tentang follow suit as a
                 centered block instead of the left-aligned label/colon/value
                 table used for Primary. --}}
            <div style="text-align: center; margin: 2mm 0 6mm 0; word-break: break-word;">
                <p style="margin: 0 0 2mm 0; font-size: 15px; font-weight: bold;">{{ strtoupper($letter->letterCode->name ?? '') }}</p>
                <p style="margin: 0; padding: 1px 0;">Nomor : {{ $letter->letter_number }}</p>
            </div>

            <div style="text-align: center; margin: 6mm auto; max-width: 100mm; word-break: break-word;">
                <p style="margin: 0; padding: 1px 0; font-weight: bold;">{{ strtoupper('Tentang '.$letter->subject) }}</p>
            </div>
        @else
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
        @endif

        {{-- Secondary is the Surat Keterangan-style layout -- it's a
             general-purpose statement, not addressed to a specific
             recipient, so the "Kepada Yth." block is hidden for it. --}}
        @if($letter->recipient && ! $isSecondaryTemplate)
            <p style="margin: 0 0 8mm 0;">
                Kepada Yth.<br>
                {!! nl2br(e($letter->recipient)) !!}<br>
                di Tempat
            </p>
        @endif

        <div class="body-content" style="text-align: justify; line-height: 1.6; margin-bottom: {{ $letter->items ? '6mm' : '14mm' }};">
            {{-- RichTextEditor bodies are already HTML (wrapped in <p> tags etc.) and render as-is.
                 Older letters saved before the rich text editor was added are plain text, so they
                 still need escaping + nl2br to preserve line breaks without showing raw tags. --}}
            @if(str_contains($letter->body, '<'))
                {!! $letter->body !!}
            @else
                {!! nl2br(e($letter->body)) !!}
            @endif
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

        {{-- Wrapped together (rather than the date as a separate sibling
             above) so a page break can never land between the two -- if
             this whole block gets pushed to the next page, the date goes
             with it, staying immediately above the signature it belongs to. --}}
        <div style="page-break-inside: avoid;">
            @if($isSecondaryTemplate)
                <p style="margin: 0 0 6mm 0; text-align: left;">{{ $dateLine }}</p>
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
                <div style="text-align: left;">
                    <p style="margin: 0;">Hormat kami,</p>
                    <p style="margin: 0; font-weight: bold;">PT. Jendela Cakra Digital</p>
                    <div style="height: 20mm;"></div>
                    <p style="margin: 0; border-top: 1px solid #1f2937; display: inline-block; padding-top: 2px;">{{ $letter->signatory_name ?? '________________________' }}</p>
                    <p style="margin: 0;">{{ $letter->signatory_title ?? '' }}</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
