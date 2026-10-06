{{-- Blank space above a signatory's name line, filled with their scanned
     signature when the document opted in ($enabled) and the name matches
     one we have on file (App\Support\SignatureImage). The image is taller
     than the space and pulled up slightly, so it overflows a little onto
     the name line the way a wet signature would, without changing the
     block's height -- documents with and without a signature lay out
     identically. Sizes are plain mm numbers since dompdf has no calc();
     the signature scans are all 2:1 (886x443).
     Params: $name, $enabled, $space (mm, default 20). --}}
@php
    $signaturePath = ($enabled ?? false) ? \App\Support\SignatureImage::pathFor($name ?? null) : null;
    $signatureSpace = $space ?? 20;
    $signatureHeight = $signatureSpace + 6;
@endphp
<div style="height: {{ $signatureSpace }}mm;">
    @if($signaturePath)
        <img src="{{ $signaturePath }}" style="height: {{ $signatureHeight }}mm; width: {{ $signatureHeight * 2 }}mm; margin-top: -3mm;">
    @endif
</div>
