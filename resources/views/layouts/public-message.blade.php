{{-- Minimal public page (no app chrome) for the few HTML responses this
     API serves: an unavailable proposal link, 404, and rate-limit notices. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · PT Jendela Cakra Digital</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f9; font-family: Helvetica, Arial, sans-serif; color: #1f2937; padding: 16px; box-sizing: border-box; }
        .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #dfe3ea; border-radius: 10px; overflow: hidden; }
        .brand { padding: 16px 28px; border-bottom: 1px solid #e6e9ef; font-size: 12px; font-weight: bold; letter-spacing: 1.5px; color: #0b1d51; }
        .body { padding: 28px; }
        .code { font-size: 12px; font-weight: bold; letter-spacing: 2px; color: #0c51d9; margin: 0 0 8px; }
        h1 { font-size: 20px; line-height: 1.3; color: #0b1d51; margin: 0 0 10px; }
        p { font-size: 14px; line-height: 1.65; color: #475569; margin: 0 0 8px; }
        a { color: #0c51d9; }
        .foot { padding: 14px 28px; background: #f8f9fb; border-top: 1px solid #e6e9ef; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">PT JENDELA CAKRA DIGITAL</div>
        <div class="body">
            <p class="code">@yield('code')</p>
            <h1>@yield('heading')</h1>
            @yield('message')
        </div>
        <div class="foot">
            Butuh bantuan? Hubungi <a href="mailto:contact@jcdigital.co.id">contact@jcdigital.co.id</a> · WhatsApp 0878 8279 2511
        </div>
    </div>
</body>
</html>
