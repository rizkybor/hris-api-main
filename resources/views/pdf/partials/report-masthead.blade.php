{{-- Page-1 letterhead strip. Params: $docType, $docDate (Carbon). --}}
<table class="masthead">
    <tr>
        <td style="width: 14mm;"><img class="logo" src="{{ public_path('images/jcd-only-color.png') }}"></td>
        <td>
            <p class="company">PT. JENDELA CAKRA DIGITAL</p>
            <p class="address">Jl. Pd. Cabe Raya No.7, Pamulang, Tangerang Selatan, Banten 15418<br>www.jcdigital.co.id &middot; contact@jcdigital.co.id &middot; +62 878-8279-2511</p>
        </td>
        <td style="width: 52mm;">
            <p class="doc-type">{{ $docType }}</p>
            <p class="doc-date">{{ $docDate->locale('id')->translatedFormat('d F Y') }}</p>
        </td>
    </tr>
</table>
<div class="rule"></div>
<div class="rule-thin"></div>
