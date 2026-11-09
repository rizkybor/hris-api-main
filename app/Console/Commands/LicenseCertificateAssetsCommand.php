<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Renders the holographic artwork of the License Certificate once, with GD,
 * into resources/license-products/ (the PNGs are committed; nothing is
 * generated per request). dompdf cannot do gradients, blend modes or
 * filters, so the foil is baked into images at ~3x print resolution.
 *
 * Silver / pearl base with recognisable holographic character:
 * - diffraction colour shift through teal-blue → periwinkle → rose-gold →
 *   champagne, strongest in one soft diagonal band (~40 % of the width,
 *   ~25°) and fading to pearl silver elsewhere;
 * - a fine diffraction grating (1 px lines, 3 px pitch) that splits the
 *   band colour into lines, one thin specular highlight along the band, and
 *   a faint repeating star motif (every ~7 mm) that shows mainly in the band;
 * - a seal whose guilloche rings shift hue around the circle (conic
 *   diffraction) with a radial sheen, dark slate ring text and a white
 *   centre for the product icon; a silver plan chip with a matching sheen.
 *
 * Variants differ only in colour intensity (a soft, b medium, c vivid but
 * refined); config('licensing.certificate_hologram') picks one. Files:
 * cert-foil[-occa]-{v}.png, cert-seal[-occa]-{v}.png, cert-chip-{v}.png
 * (the -occa files name OCCA in the micro-text / ring text).
 */
class LicenseCertificateAssetsCommand extends Command
{
    protected $signature = 'license:certificate-assets
        {--variant= : a (soft), b (medium), c (vivid) or all; default: the configured licensing.certificate_hologram}';

    protected $description = 'Render the holographic PNG assets of the license certificate (one-off; commit the result)';

    /**
     * [hue saturation, band opacity] per variant.
     */
    public const VARIANTS = [
        'a' => [0.30, 0.25],
        'b' => [0.45, 0.35],
        'c' => [0.60, 0.45],
    ];

    /** Diffraction palette: teal-blue → periwinkle → rose-gold → champagne. */
    private const PALETTE = [
        [127, 200, 232],
        [169, 156, 240],
        [240, 184, 200],
        [242, 220, 160],
    ];

    /**
     * Per-plan palettes for the key box and plan chip, the same colours as
     * the plan chip and card stripes in the app: Trial gold, Professional
     * mint → sky, Lifetime lavender → rose, Organization blue → violet.
     */
    public const PLAN_PALETTES = [
        'trial' => [[217, 166, 51], [242, 204, 89], [191, 140, 38], [230, 184, 77]],
        'professional' => [[64, 179, 128], [64, 153, 204], [89, 191, 140], [77, 128, 217]],
        'lifetime' => [[153, 102, 242], [217, 115, 191], [179, 153, 255], [115, 153, 242]],
        'organization' => [[89, 128, 242], [140, 102, 242], [77, 166, 242], [128, 115, 230]],
    ];

    /** Palette in use (the neutral one, or a plan's). */
    private array $palette = self::PALETTE;

    private const PEARL_LIGHT = [245, 246, 248];

    private const PEARL_DARK = [233, 235, 240];

    private const BORDER = [201, 205, 214];

    private string $font;

    private string $fontBold;

    private float $saturation;

    private float $opacity;

    public function handle(): int
    {
        if (! function_exists('imagettftext')) {
            $this->error('GD with FreeType is required.');

            return self::FAILURE;
        }

        $variant = strtolower((string) ($this->option('variant') ?: config('licensing.certificate_hologram', 'c')));
        $variants = $variant === 'all' ? array_keys(self::VARIANTS) : [$variant];
        foreach ($variants as $v) {
            if (! isset(self::VARIANTS[$v])) {
                $this->error('--variant must be a, b, c or all.');

                return self::FAILURE;
            }
        }

        $fonts = base_path('vendor/dompdf/dompdf/lib/fonts');
        $this->font = "{$fonts}/DejaVuSans.ttf";
        $this->fontBold = "{$fonts}/DejaVuSans-Bold.ttf";
        $out = resource_path('license-products');

        foreach ($variants as $v) {
            [$this->saturation, $this->opacity] = self::VARIANTS[$v];
            $this->line("Variant {$v}:");
            $this->save($this->foil('AUTHENTIC LICENSE  ·  JENDELA CAKRA DIGITAL  ·  '), "{$out}/cert-foil-{$v}.png");
            $this->save($this->foil('OCCA  ·  AUTHENTIC LICENSE  ·  '), "{$out}/cert-foil-occa-{$v}.png");
            $this->save($this->seal('GENUINE · LICENSE · JENDELA CAKRA DIGITAL · '), "{$out}/cert-seal-{$v}.png");
            $this->save($this->seal('GENUINE · OCCA · LICENSE · JENDELA CAKRA DIGITAL · '), "{$out}/cert-seal-occa-{$v}.png");
            $this->save($this->chip(), "{$out}/cert-chip-{$v}.png");

            // Key box and chip in each plan's colours (the seal stays silver).
            foreach (self::PLAN_PALETTES as $plan => $palette) {
                $this->palette = $palette;
                $this->save($this->foil('AUTHENTIC LICENSE  ·  JENDELA CAKRA DIGITAL  ·  '), "{$out}/cert-foil-{$v}-{$plan}.png");
                $this->save($this->foil('OCCA  ·  AUTHENTIC LICENSE  ·  '), "{$out}/cert-foil-occa-{$v}-{$plan}.png");
                $this->save($this->chip(), "{$out}/cert-chip-{$v}-{$plan}.png");
            }
            $this->palette = self::PALETTE;
        }

        $this->info('Certificate assets written to resources/license-products/.');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Key box, 176 x 45 mm at ~300 dpi.
     */
    private function foil(string $microText)
    {
        [$w, $h, $radius, $border] = [2080, 540, 36, 3];
        $im = $this->canvas($w, $h);
        $tan = tan(deg2rad(25));
        $sigma = 0.2 * $w * 0.85; // ~40 % of the width, soft edges

        for ($y = 0; $y < $h; $y++) {
            // Band centre line, leaning 25° from vertical.
            $centre = 0.55 * $w + ($h / 2 - $y) * $tan;
            for ($x = 0; $x < $w; $x++) {
                $d = $this->roundedRectDistance($x + 0.5, $y + 0.5, $w, $h, $radius);
                if ($d > 0.5) {
                    continue;
                }
                $coverage = min(1, 0.5 - $d);
                $dx = $x - $centre;

                // Pearl base, a little darker away from the light.
                $rgb = $this->mix(self::PEARL_LIGHT, self::PEARL_DARK, min(1, abs($dx) / (0.6 * $w)));

                // Diffraction colour, flowing across and along the band.
                $band = exp(-($dx / $sigma) ** 2);
                $hue = $this->diffraction(0.5 + $dx / (2.4 * $sigma) + 0.35 * $y / $h);

                // Grating: 1 px lines at 3 px pitch, parallel to the band.
                $grating = 0.5 + 0.5 * cos(2 * M_PI * ($x + ($y - $h / 2) * $tan) / 3);
                $rgb = $this->mix($rgb, $hue, $this->opacity * $band * (0.62 + 0.38 * $grating));

                // A faint second order of colour further out, for depth.
                $rgb = $this->mix($rgb, $this->diffraction(0.2 + $x / $w), 0.12 * $this->opacity * (1 - $band));

                // Thin specular highlight along the band.
                $spec = exp(-(($dx + 0.18 * $sigma) / 22) ** 2) * (0.55 + 0.45 * sin($y / $h * M_PI));
                $rgb = $this->mix($rgb, [255, 255, 255], 0.7 * $spec);

                // Very soft guilloche hairlines in neutral grey-blue.
                $m = fmod(($y + 8 * sin($x / 210 * 2 * M_PI)) / 13 + 100, 1);
                $rgb = $this->mix($rgb, [112, 124, 148], 0.06 * exp(-((min($m, 1 - $m) * 13) / 0.85) ** 2));

                if (-$d < $border) {
                    $rgb = self::BORDER;
                }

                imagesetpixel($im, $x, $y, $this->color($im, $rgb[0], $rgb[1], $rgb[2], $coverage));
            }
        }

        // Repeating star motif (~7 mm grid), visible mainly in the band.
        $pitch = 82;
        for ($gy = 0, $row = 0; $gy < $h + $pitch; $gy += $pitch * 0.75, $row++) {
            for ($gx = ($row % 2) * $pitch / 2; $gx < $w + $pitch; $gx += $pitch) {
                $centre = 0.55 * $w + ($h / 2 - $gy) * $tan;
                $band = exp(-(($gx - $centre) / $sigma) ** 2);
                $strength = 0.08 + 0.55 * $band * ($this->opacity / 0.45);
                $hue = $this->diffraction(0.5 + ($gx - $centre) / (2.4 * $sigma) + 0.35 * $gy / $h);
                $tone = $this->mix([150, 156, 175], $this->darken($hue, 0.35), $band);
                $ink = imagecolorallocatealpha($im, $this->clamp($tone[0]), $this->clamp($tone[1]), $this->clamp($tone[2]), (int) round(127 * (1 - min(0.6, $strength))));
                imagettftext($im, 15, 0, (int) $gx - 8, (int) $gy + 8, $ink, $this->font, '✦');
            }
        }

        // Light micro-text along the bottom edge.
        $ink = imagecolorallocatealpha($im, 170, 175, 188, 40);
        imagettftext($im, 10, 0, $radius + 24, $h - 18, $ink, $this->font, str_repeat($microText, 40));

        $this->clipToRoundedRect($im, $w, $h, $radius, $border);

        return $im;
    }

    /**
     * Round seal (~27 mm), rotated a little: silver disc, guilloche rings
     * whose hue shifts around the circle, a radial sheen, ring text and a
     * white centre disc for the product icon.
     */
    private function seal(string $ringText)
    {
        $s = 600;
        $c = $s / 2;
        $R = 290;
        $im = $this->canvas($s, $s);

        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                $dx = $x + 0.5 - $c;
                $dy = $y + 0.5 - $c;
                $r = sqrt($dx * $dx + $dy * $dy);
                if ($r > $R + 0.5) {
                    continue;
                }
                $coverage = min(1, $R + 0.5 - $r);
                $a = atan2($dy, $dx);
                $turn = fmod($a / (2 * M_PI) + 1, 1);

                // Silver disc with a radial sheen (bright upper-left lobe).
                $rgb = $this->mix([242, 243, 247], [206, 210, 219], ($r / $R) ** 1.5);
                $sheen = max(0, cos($a + 2.3)) ** 2 * exp(-(($r / $R - 0.55) / 0.35) ** 2);
                $rgb = $this->mix($rgb, [255, 255, 255], 0.55 * $sheen);

                // Conic diffraction: a faint wash plus coloured guilloche rings.
                $hue = $this->diffraction($turn * 2 + 0.15 * $r / $R);
                $rgb = $this->mix($rgb, $hue, 0.7 * $this->opacity);
                $ring = abs(sin($r / 2.6 + 0.8 * sin(12 * $a) * ($r / $R)));
                $line = exp(-($ring / 0.16) ** 2);
                $rgb = $this->mix($rgb, $this->darken($hue, 0.12), min(0.85, 0.3 + 1.3 * $this->opacity) * $line);

                // Text band framed by two thin lines, rim.
                if ($r > $R - 66 && $r < $R - 10) {
                    $rgb = $this->mix([228, 231, 237], $hue, 0.45 * $this->opacity);
                    $rgb = $this->mix($rgb, [255, 255, 255], 0.4 * $sheen);
                }
                if (abs($r - ($R - 67)) < 1.6 || abs($r - ($R - 9)) < 1.6) {
                    $rgb = [160, 166, 180];
                }
                if ($r >= $R - 2.5) {
                    $rgb = [150, 156, 170];
                }

                // White centre disc for the product icon.
                if ($r < 118) {
                    $rgb = [255, 255, 255];
                } elseif ($r < 121) {
                    $rgb = [170, 175, 188];
                }

                imagesetpixel($im, $x, $y, $this->color($im, $rgb[0], $rgb[1], $rgb[2], $coverage));
            }
        }

        // Ring text in dark slate (#3A3F4B), letter by letter.
        $ink = imagecolorallocate($im, 58, 63, 75);
        $chars = preg_split('//u', $ringText, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($chars);
        $radius = $R - 47;
        foreach ($chars as $i => $char) {
            $theta = 2 * M_PI * $i / $count;
            $box = imagettfbbox(18, 0, $this->fontBold, $char);
            $half = ($box[2] - $box[0]) / 2;
            $px = $c + $radius * sin($theta) - $half * cos($theta);
            $py = $c - $radius * cos($theta) - $half * sin($theta);
            imagettftext($im, 18, -rad2deg($theta), (int) round($px), (int) round($py), $ink, $this->fontBold, $char);
        }

        $rotated = imagerotate($im, 8, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagesavealpha($rotated, true);
        $out = $this->canvas($s, $s);
        imagealphablending($out, false);
        imagecopy($out, $rotated, 0, 0, (int) ((imagesx($rotated) - $s) / 2), (int) ((imagesy($rotated) - $s) / 2), $s, $s);

        return $out;
    }

    /**
     * Plan pill (34 x 6 mm): silver with a small iridescent sheen and
     * grating, thin cool-grey border.
     */
    private function chip()
    {
        [$w, $h] = [400, 72];
        $im = $this->canvas($w, $h);
        $radius = $h / 2;
        $tan = tan(deg2rad(25));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $d = $this->roundedRectDistance($x + 0.5, $y + 0.5, $w, $h, $radius);
                if ($d > 0.5) {
                    continue;
                }
                $rgb = $this->mix(self::PEARL_LIGHT, self::PEARL_DARK, $y / $h);
                $dx = $x - (0.55 * $w + ($h / 2 - $y) * $tan);
                $band = exp(-($dx / 120) ** 2);
                $grating = 0.5 + 0.5 * cos(2 * M_PI * ($x + $y * $tan) / 3);
                $rgb = $this->mix($rgb, $this->diffraction(0.5 + $dx / 300), 1.1 * $this->opacity * $band * (0.65 + 0.35 * $grating));
                $rgb = $this->mix($rgb, [255, 255, 255], 0.6 * exp(-(($dx + 25) / 8) ** 2));
                if (-$d < 2.5) {
                    $rgb = self::BORDER;
                }
                imagesetpixel($im, $x, $y, $this->color($im, $rgb[0], $rgb[1], $rgb[2], min(1, 0.5 - $d)));
            }
        }

        return $im;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Diffraction colour at $t (wraps), at the variant's saturation: the
     * palette colour pulled towards its own grey.
     */
    private function diffraction(float $t): array
    {
        $n = count($this->palette);
        $t = fmod(fmod($t, 1) + 1, 1) * $n;
        $i = (int) floor($t);
        $rgb = $this->mix($this->palette[$i % $n], $this->palette[($i + 1) % $n], $t - $i);
        $grey = 0.3 * $rgb[0] + 0.59 * $rgb[1] + 0.11 * $rgb[2];

        // The palette itself is ~55 % saturated; scale to the variant.
        return $this->mix([$grey, $grey, $grey], $rgb, min(1, $this->saturation / 0.55));
    }

    private function darken(array $rgb, float $f): array
    {
        return $this->mix($rgb, [40, 44, 60], $f);
    }

    private function canvas(int $w, int $h)
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);
        imageantialias($im, true);

        return $im;
    }

    private function save($im, string $path): void
    {
        imagepng($im, $path, 9);
        $this->line(sprintf('  %-24s %4dx%-4d %6.1f KB', basename($path), imagesx($im), imagesy($im), filesize($path) / 1024));
    }

    private function color($im, float $r, float $g, float $b, float $coverage): int
    {
        $alpha = (int) round(127 * (1 - max(0, min(1, $coverage))));

        return imagecolorallocatealpha($im, $this->clamp($r), $this->clamp($g), $this->clamp($b), $alpha);
    }

    private function clamp(float $v): int
    {
        return (int) max(0, min(255, round($v)));
    }

    private function mix(array $a, array $b, float $f): array
    {
        $f = max(0, min(1, $f));

        return [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f, $a[2] + ($b[2] - $a[2]) * $f];
    }

    /**
     * Signed distance to a rounded rectangle (negative inside).
     */
    private function roundedRectDistance(float $x, float $y, float $w, float $h, float $r): float
    {
        $qx = abs($x - $w / 2) - ($w / 2 - $r);
        $qy = abs($y - $h / 2) - ($h / 2 - $r);

        return sqrt(max($qx, 0) ** 2 + max($qy, 0) ** 2) + min(max($qx, $qy), 0) - $r;
    }

    /**
     * Make everything outside the rounded rectangle fully transparent again
     * and repaint the border clean after drawing the motif and text.
     */
    private function clipToRoundedRect($im, int $w, int $h, float $radius, int $border): void
    {
        imagealphablending($im, false);
        $clear = imagecolorallocatealpha($im, 255, 255, 255, 127);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $d = $this->roundedRectDistance($x + 0.5, $y + 0.5, $w, $h, $radius);
                if ($d > 0.5) {
                    imagesetpixel($im, $x, $y, $clear);
                } elseif (-$d < $border) {
                    imagesetpixel($im, $x, $y, $this->color($im, self::BORDER[0], self::BORDER[1], self::BORDER[2], min(1, 0.5 - $d)));
                }
            }
        }
        imagealphablending($im, true);
    }
}
