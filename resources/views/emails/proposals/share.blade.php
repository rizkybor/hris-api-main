<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proposal Penawaran — {{ $proposal->title }}</title>
</head>
{{-- Table layout with inline styles: the only thing every email client
     (Gmail, Outlook, mobile apps) renders consistently. Solid colours only. --}}
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:Helvetica, Arial, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #dfe3ea; border-radius:8px;">
                {{-- Header --}}
                <tr>
                    <td style="padding:22px 32px; border-bottom:1px solid #e6e9ef;">
                        <span style="font-size:13px; font-weight:bold; letter-spacing:1.5px; color:#0b1d51;">PT JENDELA CAKRA DIGITAL</span><br>
                        <span style="font-size:12px; color:#64748b;">Software House &amp; Digital Agency</span>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:30px 32px 8px 32px;">
                        <p style="margin:0 0 6px 0; font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#0c51d9;">PROPOSAL PENAWARAN</p>
                        <h1 style="margin:0 0 22px 0; font-size:22px; line-height:1.3; color:#0b1d51;">{{ $proposal->title }}</h1>

                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.65;">{{ $greeting }}</p>
                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.65;">
                            Terima kasih atas waktu dan kesempatan yang telah diberikan kepada kami. Bersama email ini, kami sampaikan
                            Proposal Penawaran <strong>{{ $proposal->title }}</strong> yang kami susun untuk <strong>{{ $proposal->client_name }}</strong>.
                        </p>

                        @if(filled($personalMessage))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">
                                <tr>
                                    <td style="border-left:3px solid #0c51d9; background-color:#f3f6fb; padding:12px 16px; font-size:14px; line-height:1.65; color:#1f2937;">
                                        {!! nl2br(e($personalMessage)) !!}
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <p style="margin:0 0 22px 0; font-size:15px; line-height:1.65;">
                            Proposal dapat dibuka dan diunduh melalui tombol di bawah ini.
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
                            <tr>
                                <td style="background-color:#0b1d51; border-radius:6px;">
                                    <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">Lihat Proposal</a>
                                </td>
                            </tr>
                        </table>

                        {{-- Document details --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e9ef; border-bottom:1px solid #e6e9ef; margin:0 0 22px 0;">
                            <tr>
                                <td style="padding:10px 0; font-size:13px; color:#64748b; width:42%;">Klien</td>
                                <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold;">{{ $proposal->client_name }}</td>
                            </tr>
                            @if($proposal->letter_number)
                                <tr>
                                    <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Nomor Surat</td>
                                    <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $proposal->letter_number }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Tanggal</td>
                                <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $date }}</td>
                            </tr>
                            @if($expiresAt)
                                <tr>
                                    <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Tautan berlaku hingga</td>
                                    <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $expiresAt }}</td>
                                </tr>
                            @endif
                        </table>

                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.65;">
                            Apabila ada pertanyaan atau bagian yang ingin disesuaikan, silakan membalas email ini atau menghubungi kami.
                            Kami juga siap melakukan presentasi singkat untuk menjelaskan penawaran ini secara langsung.
                        </p>

                        <p style="margin:22px 0 2px 0; font-size:15px; line-height:1.6;">Hormat kami,</p>
                        @if($proposal->signatory_name)
                            <p style="margin:0; font-size:15px; font-weight:bold; color:#0b1d51;">{{ $proposal->signatory_name }}</p>
                        @endif
                        @if($proposal->signatory_title)
                            <p style="margin:0; font-size:13px; color:#64748b;">{{ $proposal->signatory_title }}</p>
                        @endif
                        <p style="margin:0 0 26px 0; font-size:13px; color:#64748b;">PT Jendela Cakra Digital</p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:18px 32px; background-color:#f8f9fb; border-top:1px solid #e6e9ef; border-radius:0 0 8px 8px; font-size:12px; line-height:1.7; color:#64748b;">
                        PT Jendela Cakra Digital<br>
                        Jl. Pd. Cabe Raya No.7, Pamulang, Tangerang Selatan, Banten 15418<br>
                        <a href="mailto:contact@jcdigital.co.id" style="color:#0c51d9; text-decoration:none;">contact@jcdigital.co.id</a>
                        &nbsp;·&nbsp; WhatsApp 0878 8279 2511 &nbsp;·&nbsp;
                        <a href="https://jcdigital.co.id" style="color:#0c51d9; text-decoration:none;">jcdigital.co.id</a>
                        <br><br>
                        Jika tombol di atas tidak berfungsi, salin tautan berikut ke browser Anda:<br>
                        <a href="{{ $url }}" style="color:#0c51d9; word-break:break-all;">{{ $url }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
