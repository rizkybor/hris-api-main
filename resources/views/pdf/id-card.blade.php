<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: "Helvetica", "Arial", sans-serif;
            margin: 0;
            width: 242.65pt;
            height: 153.05pt;
            background-color: #0b1d51;
            color: #ffffff;
            overflow: hidden;
        }
        .card {
            width: 100%;
            padding: 10pt 12pt;
            overflow: hidden;
        }
        .clear { clear: both; }

        .header { width: 100%; }
        .logo { float: left; width: 16pt; height: 16pt; }
        .brand {
            float: left;
            margin-left: 5pt;
            font-size: 7pt;
            font-weight: bold;
            line-height: 1.25;
            letter-spacing: 0.5pt;
            color: #ffffff;
        }
        .badge {
            float: right;
            margin-right: 12pt;
            font-size: 5.5pt;
            font-weight: bold;
            letter-spacing: 0.4pt;
            color: #0b1d51;
            background-color: #ffffff;
            padding: 2pt 5pt;
            border-radius: 6pt;
        }

        .body { width: 100%; margin-top: 8pt; }
        .photo-wrap { float: left; width: 46pt; }
        .photo {
            width: 42pt;
            height: 42pt;
            border-radius: 21pt;
            border: 1.5pt solid #ffffff;
        }
        .photo-fallback {
            width: 42pt;
            height: 42pt;
            border-radius: 21pt;
            border: 1.5pt solid #ffffff;
            background-color: #2b3f7a;
            color: #ffffff;
            font-size: 15pt;
            font-weight: bold;
            text-align: center;
            line-height: 42pt;
        }
        .info { float: left; width: 165pt; }
        .name {
            font-size: 11.5pt;
            font-weight: bold;
            color: #ffffff;
            margin: 0 0 1pt 0;
        }
        .job-title {
            font-size: 8pt;
            color: #93c5fd;
            margin: 0 0 5pt 0;
        }
        .meta-row { margin: 1pt 0; font-size: 6.5pt; }
        .meta-label { display: inline-block; width: 32pt; color: #93a4c9; }
        .meta-value { color: #ffffff; font-weight: bold; }

        .footer {
            width: 100%;
            margin-top: 16pt;
            border-top: 0.5pt solid #2b3f7a;
            padding-top: 4pt;
        }
        .code {
            float: left;
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 0.3pt;
            color: #ffffff;
        }
        .valid {
            float: right;
            margin-right: 12pt;
            font-size: 5pt;
            color: #93a4c9;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <img class="logo" src="{{ public_path('images/jcd-only-color.png') }}">
            <div class="brand">JENDELA CAKRA<br>DIGITAL</div>
            <span class="badge">ID CARD</span>
            <div class="clear"></div>
        </div>

        <div class="body">
            <div class="photo-wrap">
                @if($photoDataUri)
                    <img class="photo" src="{{ $photoDataUri }}">
                @else
                    <div class="photo-fallback">{{ strtoupper(substr($employee->user->name ?? '?', 0, 1)) }}</div>
                @endif
            </div>
            <div class="info">
                <p class="name">{{ $employee->user->name ?? '-' }}</p>
                <p class="job-title">{{ ucwords($employee->jobInformation->job_title ?? '-') }}</p>
                <div class="meta-row"><span class="meta-label">Team</span><span class="meta-value">{{ $employee->jobInformation->team->name ?? '-' }}</span></div>
                <div class="meta-row"><span class="meta-label">Joined</span><span class="meta-value">{{ $employee->jobInformation?->start_date?->translatedFormat('d M Y') ?? '-' }}</span></div>
            </div>
            <div class="clear"></div>
        </div>

        <div class="footer">
            <span class="code">{{ $employee->code }}</span>
            <span class="valid">Company Property</span>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
