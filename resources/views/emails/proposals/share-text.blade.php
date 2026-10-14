{{ $greeting }}

Terima kasih atas waktu dan kesempatan yang telah diberikan kepada kami. Bersama email ini, kami sampaikan Proposal Penawaran {{ $proposal->title }} yang kami susun untuk {{ $proposal->client_name }}.
@if(filled($personalMessage))

{{ $personalMessage }}
@endif

Proposal dapat dibuka dan diunduh melalui tautan berikut:
{{ $url }}

Klien: {{ $proposal->client_name }}
@if($proposal->letter_number)
Nomor Surat: {{ $proposal->letter_number }}
@endif
Tanggal: {{ $date }}
@if($expiresAt)
Tautan berlaku hingga: {{ $expiresAt }}
@endif

Apabila ada pertanyaan atau bagian yang ingin disesuaikan, silakan membalas email ini atau menghubungi kami.

Hormat kami,
{{ $proposal->signatory_name }}
{{ $proposal->signatory_title }}
PT Jendela Cakra Digital
contact@jcdigital.co.id · WhatsApp 0878 8279 2511 · jcdigital.co.id
