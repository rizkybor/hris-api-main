<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'Anton';
            font-weight: normal;
            font-style: normal;
            src: url('{{ public_path('fonts/Anton-Regular.ttf') }}') format('truetype');
        }
        /* Anton ships as a single (already very bold/black) weight -- this
           duplicate registration under font-weight:bold exists purely so
           dompdf resolves `font-weight:bold` to this same file instead of
           silently falling back to plain Helvetica (it has no bold face to
           match otherwise, since only the "normal" weight is registered
           above). */
        @font-face {
            font-family: 'Anton';
            font-weight: bold;
            font-style: normal;
            src: url('{{ public_path('fonts/Anton-Regular.ttf') }}') format('truetype');
        }
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: "Helvetica", "Arial", sans-serif;
            margin: 0;
            width: 153.07pt;
            height: 242.69pt;
            overflow: hidden;
        }
        .side {
            position: relative;
            width: 153.07pt;
            height: 242.69pt;
            overflow: hidden;
        }
        .side + .side { page-break-before: always; }
        .bg {
            position: absolute; top: 0; left: 0; width: 153.07pt; height: 242.69pt;
        }

        /* ============ FRONT ============ */
        /* The name is pre-wrapped into (at most) 2 lines in PHP and each
           line placed at a fixed position, rather than one auto-wrapping
           block: dompdf both ignores line-height on this embedded font
           (renders ~1.6x the requested value) and doesn't reliably clip
           overflow past max-height, so a long name could silently wrap to
           a 3rd line and collide with the rule/job-title below it. Fixed
           per-line positions make that impossible regardless of length. */
        .name-line {
            position: absolute; left: 14pt; right: 12pt;
            font-family: 'Anton', 'Helvetica', sans-serif;
            font-weight: bold;
            color: #ffffff;
            font-size: 14pt;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        .name-line-1 { top: 90pt; }
        .name-line-2 { top: 108pt; }
        .rule {
            position: absolute; top: 150pt; left: 14pt; width: 24pt; height: 2pt; background-color: #ffffff;
        }
        .job-title {
            position: absolute; top: 156pt; left: 14pt; right: 14pt;
            color: #ffffff; font-size: 9pt; white-space: nowrap; overflow: hidden;
        }
        .panel { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .panel span { position: absolute; font-size: 5.5pt; color: #1a1a2e; text-align: left; }
        .panel .label { left: 14pt; font-weight: bold; }
        .panel .colon { left: 52pt; }
        .panel .value { left: 58pt; right: 6pt; white-space: nowrap; overflow: hidden; }
    </style>
</head>
<body>
    <!-- FRONT -- design team's background artwork, with the employee's
         name/title/id/contact overlaid at the positions from their example. -->
    <div class="side">
        <img class="bg" src="{{ public_path('images/idcard-front-bg.png') }}">

        @php
            // The degree/title suffix (e.g. ", S.Kom.") is treated as part
            // of the word it's attached to, never split from it. Line 1
            // always takes the first two name words; everything after that
            // -- including the trailing degree -- goes to line 2. A name of
            // two words or fewer (with or without a degree) fits entirely
            // on line 1. $maxChars is a truncation safety net only (tuned
            // for Anton 20pt within the ~127pt-wide name column).
            $maxChars = 28;
            $nameWords = array_values(array_filter(preg_split('/\s+/', trim($employee->user->name ?? '-'))));
            $commaIdx = null;
            foreach ($nameWords as $i => $word) {
                if (str_contains($word, ',')) {
                    $commaIdx = $i;
                    break;
                }
            }
            $nameWordCount = $commaIdx === null ? count($nameWords) : $commaIdx + 1;
            if ($nameWordCount > 2) {
                $nameLine1 = implode(' ', array_slice($nameWords, 0, 2));
                $nameLine2 = implode(' ', array_slice($nameWords, 2));
            } else {
                $nameLine1 = implode(' ', $nameWords);
                $nameLine2 = '';
            }
            if (mb_strlen($nameLine1) > $maxChars) {
                $nameLine1 = mb_substr($nameLine1, 0, $maxChars - 1).'…';
            }
            if (mb_strlen($nameLine2) > $maxChars) {
                $nameLine2 = mb_substr($nameLine2, 0, $maxChars - 1).'…';
            }
        @endphp
        <div class="name-line name-line-1">{{ $nameLine1 }}</div>
        @if($nameLine2 !== '')
            <div class="name-line name-line-2">{{ $nameLine2 }}</div>
        @endif
        <div class="rule"></div>
        <div class="job-title">{{ \Illuminate\Support\Str::limit(ucwords($employee->jobInformation->job_title ?? '-'), 28) }}</div>

        <div class="panel">
            <span class="label" style="top: 207.8pt;">Employee Id</span><span class="colon" style="top: 207.8pt;">:</span><span class="value" style="top: 207.8pt;">{{ $employee->code }}</span>
            <span class="label" style="top: 216.8pt;">Phone</span><span class="colon" style="top: 216.8pt;">:</span><span class="value" style="top: 216.8pt;">{{ \Illuminate\Support\Str::limit($employee->phone ?? '-', 24) }}</span>
            <span class="label" style="top: 225.8pt;">Email</span><span class="colon" style="top: 225.8pt;">:</span><span class="value" style="top: 225.8pt;">{{ \Illuminate\Support\Str::limit($employee->user->email ?? '-', 26) }}</span>
        </div>
    </div>

    <!-- BACK -- the design team's own artwork, unchanged: no per-employee
         content belongs here, so it's just the full-bleed background. -->
    <div class="side">
        <img class="bg" src="{{ public_path('images/idcard-back-bg.png') }}">
    </div>
</body>
</html>
