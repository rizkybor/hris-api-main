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

        /* ============ FRONT (v4.0) ============
           Background is the design team's artwork (logo + white contact
           strip from 196pt). On top: portrait photo (only when the user has
           one), name,
           job title, and the contact rows on the white strip. The web
           preview (EmployeeProfile.vue) uses these same pt values.

           The name is pre-wrapped into (at most) 2 lines in PHP and each
           line placed at a fixed position: dompdf ignores line-height on
           the embedded font and does not clip overflow reliably. */
        .photo {
            position: absolute; top: 62pt; left: 14pt; width: 56pt; height: 67pt;
            border: 1.5pt solid #ffffff; border-radius: 6pt;
        }
        .name-line {
            position: absolute; left: 14pt; right: 12pt;
            font-family: 'Anton', 'Helvetica', sans-serif;
            font-weight: bold;
            color: #ffffff;
            font-size: 13pt;
            line-height: 1;
            white-space: nowrap;
            overflow: hidden;
        }
        .job-title {
            position: absolute; left: 14pt; right: 14pt;
            color: #c9d3ee; font-size: 8pt; white-space: nowrap; overflow: hidden;
        }
        .row { position: absolute; left: 14pt; right: 10pt; }
        .row .label {
            position: absolute; left: 0; width: 44pt;
            font-size: 5pt; letter-spacing: 0.4pt; text-transform: uppercase; color: #667085;
        }
        .row .value {
            position: absolute; left: 47pt; right: 0;
            font-size: 6pt; color: #0f1b2d; white-space: nowrap; overflow: hidden;
        }
        .row .mono { font-family: 'Courier', monospace; font-weight: bold; font-size: 6.5pt; }
    </style>
</head>
<body>
    <!-- FRONT -- design team's background artwork with the employee's
         photo, name, title and contact rows overlaid (HRIS+ v4.0 layout). -->
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
        @php
            // Without a photo nothing is drawn in its place; the name block
            // moves up into that space instead.
            $nameTop = $photo ? 139 : 90;
            $jobTop = $nameTop + ($nameLine2 !== '' ? 36 : 20);
        @endphp
        @if($photo)
            <img class="photo" src="{{ $photo }}">
        @endif

        <div class="name-line" style="top: {{ $nameTop }}pt;">{{ $nameLine1 }}</div>
        @if($nameLine2 !== '')
            <div class="name-line" style="top: {{ $nameTop + 16 }}pt;">{{ $nameLine2 }}</div>
        @endif
        <div class="job-title" style="top: {{ $jobTop }}pt;">{{ \Illuminate\Support\Str::limit(ucwords($employee->jobInformation->job_title ?? '-'), 32) }}</div>

        <div class="row" style="top: 204pt;"><span class="label">Employee ID</span><span class="value mono">{{ $employee->code }}</span></div>
        <div class="row" style="top: 216pt;"><span class="label">Phone</span><span class="value">{{ \Illuminate\Support\Str::limit($employee->phone ?? '-', 26) }}</span></div>
        <div class="row" style="top: 228pt;"><span class="label">Email</span><span class="value">{{ \Illuminate\Support\Str::limit($employee->user->email ?? '-', 30) }}</span></div>
    </div>

    <!-- BACK -- the design team's own artwork, unchanged: no per-employee
         content belongs here, so it's just the full-bleed background. -->
    <div class="side">
        <img class="bg" src="{{ public_path('images/idcard-back-bg.png') }}">
    </div>
</body>
</html>
