{{-- Public over-the-air install page for an iPad build (single-use link).
     Variables: lang (en|id), status (active|used|expired|revoked|unavailable|not_found),
     productName, icon (data URI or null), version, expiresAt, usedAt, itms_url. --}}
@php
    $t = [
        'en' => [
            'title' => 'Install :app',
            'version' => 'Version :version for iPad',
            'button' => 'Install :app',
            'steps' => [
                'Open this page in Safari on the iPad this link was made for.',
                'Tap “Install :app”, then “Install” in the prompt. The app appears on the Home Screen.',
                'This link works once and expires on :date.',
            ],
            'note' => 'If the installation fails with “Unable to install”, this iPad is not registered for this build. Send us its details and we will make a new link.',
            'trust' => 'No extra settings are needed. Only if iPadOS says “Untrusted Developer”: Settings › General › VPN & Device Management, tap the developer, then Trust.',
            'used' => 'This install link has already been used.',
            'expired' => 'This install link has expired.',
            'revoked' => 'This install link has been revoked.',
            'unavailable' => 'This version can no longer be installed from this link.',
            'not_found' => 'This install link does not exist.',
            'used_at' => 'Used on :date.',
            'again' => 'Each link installs the app once. For a new installation, ask for a new link.',
            'switch' => 'Bahasa Indonesia',
            'help' => 'Need help?',
        ],
        'id' => [
            'title' => 'Pasang :app',
            'version' => 'Versi :version untuk iPad',
            'button' => 'Pasang :app',
            'steps' => [
                'Buka halaman ini di Safari pada iPad yang dituju oleh tautan ini.',
                'Ketuk “Pasang :app”, lalu “Install” pada konfirmasi. Aplikasi muncul di Layar Utama.',
                'Tautan ini hanya berlaku sekali dan kedaluwarsa pada :date.',
            ],
            'note' => 'Jika pemasangan gagal (“Unable to install”), iPad ini belum terdaftar untuk build ini. Kirimkan data iPad Anda, kami buatkan tautan baru.',
            'trust' => 'Tidak perlu pengaturan tambahan. Hanya jika iPadOS menampilkan “Untrusted Developer”: Pengaturan › Umum › VPN & Manajemen Perangkat, ketuk pengembangnya, lalu Percayai.',
            'used' => 'Tautan pemasangan ini sudah digunakan.',
            'expired' => 'Tautan pemasangan ini sudah kedaluwarsa.',
            'revoked' => 'Tautan pemasangan ini sudah dibatalkan.',
            'unavailable' => 'Versi ini tidak bisa lagi dipasang dari tautan ini.',
            'not_found' => 'Tautan pemasangan ini tidak ditemukan.',
            'used_at' => 'Digunakan pada :date.',
            'again' => 'Setiap tautan hanya untuk satu kali pemasangan. Untuk memasang lagi, minta tautan baru.',
            'switch' => 'English',
            'help' => 'Butuh bantuan?',
        ],
    ][$lang];
    $date = fn ($value) => $value ? $value->locale($lang)->translatedFormat('j F Y, H:i').' WIB' : '';
    $r = fn ($text) => strtr($text, [':app' => $productName, ':version' => (string) $version, ':date' => $date($expiresAt)]);
    $other = $lang === 'id' ? 'en' : 'id';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $r($t['title']) }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, Helvetica, Arial, sans-serif; color: #1f2937; padding: 16px; box-sizing: border-box; }
        .card { width: 100%; max-width: 420px; background: #fff; border: 1px solid #dfe3ea; border-radius: 14px; overflow: hidden; text-align: center; }
        .body { padding: 28px 24px 20px; }
        .icon { width: 88px; height: 88px; border-radius: 20px; box-shadow: 0 2px 8px rgba(11, 29, 81, .15); }
        .placeholder { width: 88px; height: 88px; border-radius: 20px; background: #0c51d9; color: #fff; font-size: 30px; font-weight: bold; line-height: 88px; margin: 0 auto; }
        h1 { font-size: 22px; color: #0b1d51; margin: 14px 0 4px; }
        .version { font-size: 14px; color: #64748b; margin: 0 0 20px; }
        .button { display: block; background: #0c51d9; color: #fff; text-decoration: none; font-size: 17px; font-weight: 600; padding: 14px; border-radius: 12px; }
        .button:active { background: #0a43b5; }
        ol { text-align: left; font-size: 14px; line-height: 1.6; color: #475569; padding-left: 20px; margin: 20px 0 12px; }
        p { font-size: 13px; line-height: 1.6; color: #64748b; margin: 0 0 10px; text-align: left; }
        .message { font-size: 16px; color: #0b1d51; font-weight: 600; text-align: center; margin: 4px 0 10px; }
        .center { text-align: center; }
        .foot { padding: 12px 24px; background: #f8f9fb; border-top: 1px solid #e6e9ef; font-size: 12px; color: #64748b; }
        a { color: #0c51d9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="body">
            @if ($icon)
                <img class="icon" src="{{ $icon }}" alt="">
            @else
                <div class="placeholder">{{ mb_substr($productName, 0, 1) }}</div>
            @endif
            <h1>{{ $productName }}</h1>
            @if ($version)
                <p class="version center">{{ $r($t['version']) }}</p>
            @endif

            @if ($status === 'active')
                <a class="button" href="{{ $itms_url }}">{{ $r($t['button']) }}</a>
                <ol>
                    @foreach ($t['steps'] as $step)
                        <li>{{ $r($step) }}</li>
                    @endforeach
                </ol>
                <p>{{ $t['note'] }}</p>
                <p>{{ $t['trust'] }}</p>
            @else
                <p class="message">{{ $t[$status] ?? $t['not_found'] }}</p>
                @if ($status === 'used' && $usedAt)
                    <p class="center">{{ strtr($t['used_at'], [':date' => $date($usedAt)]) }}</p>
                @endif
                <p class="center">{{ $t['again'] }}</p>
            @endif
        </div>
        <div class="foot">
            <a href="?lang={{ $other }}">{{ $t['switch'] }}</a> ·
            {{ $t['help'] }} <a href="mailto:contact@jcdigital.co.id">contact@jcdigital.co.id</a>
        </div>
    </div>
</body>
</html>
