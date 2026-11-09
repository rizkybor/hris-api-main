<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseProduct;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the License Certificate PDF (A4 portrait): page 1 is the
 * certificate, followed by "Appendix A" with the product's license terms
 * when the product has any. Images are embedded as base64.
 */
class LicenseCertificateService
{
    public const ACCENT = '#F2821F';

    /** Plan chip text, the plan's strong colour in the app. */
    public const PLAN_INK = [
        'trial' => '#735810',
        'professional' => '#3F6B4A',
        'lifetime' => '#7A2BC2',
        'organization' => '#315E85',
    ];

    public const VERIFY_DOMAIN = 'hris.jcdigital.co.id';

    /**
     * Dates on the certificate are in the company's time zone.
     */
    public const TIMEZONE = 'Asia/Jakarta';

    public function render(License $license): string
    {
        $pdf = Pdf::loadView('pdf.license-certificate', $this->data($license))
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true, 'dpi' => 144]);

        // "Page X of Y" bottom right on every page (dompdf has no
        // counter(pages) in CSS).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 48.2 - 54,
            $canvas->get_height() - 38.3 - 6,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            6.6,
            [0.42, 0.435, 0.51]
        );

        return $dompdf->output();
    }

    public function filename(License $license): string
    {
        $product = $license->product;

        return Str::slug($product?->name ?: 'License', '-').'-License-'.$license->key.'.pdf';
    }

    /**
     * LIC-OCCA-0001: product code and the license's number in the product.
     */
    public static function reference(License $license): string
    {
        return 'LIC-'.strtoupper($license->product?->code ?: 'KEY').'-'.str_pad((string) ($license->sequence ?? $license->id), 4, '0', STR_PAD_LEFT);
    }

    public function data(License $license): array
    {
        $license->loadMissing('product');
        $product = $license->product;
        $issuedAt = $license->created_at ?? now();
        $termsVersion = $license->terms_version ?: $product?->terms_version;
        $hasTerms = filled($product?->terms);

        return [
            'license' => $license,
            'product' => $product,
            'accent' => self::ACCENT,
            'reference' => self::reference($license),
            'verifyDomain' => self::VERIFY_DOMAIN,
            'issuedOn' => self::date($issuedAt),
            'startsOn' => self::date($license->starts_at ?? $issuedAt),
            'endsOn' => $license->expires_at ? self::date($license->expires_at) : null,
            'durationLabel' => $this->durationLabel($license),
            'statusLabel' => Str::of($license->status)->replace('_', ' ')->title()->toString(),
            'categoryLabel' => ucfirst($license->category()),
            // Organization plan = organization category: show it once.
            'showCategory' => strcasecmp($license->planLabel(), ucfirst($license->category())) !== 0,
            'organizationTypeLabel' => $license->organizationTypeLabel(),
            'steps' => $this->activationSteps($product),
            'productIcon' => $this->productIcon($product),
            'companyLogo' => self::embed(resource_path('license-products/jcd-logo.png')),
            // Holographic artwork (php artisan license:certificate-assets),
            // in the configured variant; product-specific when available.
            // Key box and plan chip in the plan's colours (as in the app).
            'foil' => self::embed(self::asset('cert-foil', $product, $license->plan)),
            'seal' => self::embed(self::asset('cert-seal', $product)),
            'chip' => self::embed(self::asset('cert-chip', null, $license->plan)),
            'planInk' => self::PLAN_INK[$license->plan] ?? '#14161F',
            'band' => self::embed(resource_path('license-products/certificate-band.png')),
            'hasTerms' => $hasTerms,
            'termsVersion' => $termsVersion,
            'termsEffectiveOn' => $product?->terms_effective_at ? self::date($product->terms_effective_at) : null,
            'termsHtml' => $hasTerms ? Str::markdown($product->terms, ['html_input' => 'escape', 'allow_unsafe_links' => false]) : null,
        ];
    }

    /**
     * "10 November 2026"
     */
    public static function date($value): string
    {
        return Carbon::parse($value)->timezone(self::TIMEZONE)->format('j F Y');
    }

    private function durationLabel(License $license): string
    {
        $days = $license->durationDays();
        if ($days === null) {
            return 'Lifetime · never expires';
        }

        $start = $license->periodStart();
        $months = $start ? (int) round($start->diffInMonths($license->expires_at, true)) : 0;
        if ($months >= 12 && $months % 12 === 0) {
            $years = intdiv($months, 12);

            return $years.' '.Str::plural('year', $years)." ({$days} days)";
        }
        if ($months >= 1 && $days >= 28) {
            return $months.' '.Str::plural('month', $months)." ({$days} days)";
        }

        return $days.' '.Str::plural('day', $days);
    }

    /**
     * Three short steps for this product, plus the shared notes.
     */
    private function activationSteps(?LicenseProduct $product): array
    {
        $name = $product?->name ?: 'the app';

        $steps = $product?->code === LicenseProduct::OCCA
            ? [
                ['Open OCCA', 'Launch OCCA on your iPad or Mac and go to the project list.'],
                ['Open License', 'Tap the plan chip (Starter) next to the OCCA name at the top of the project list.'],
                ['Paste the key and activate', 'Paste the license key exactly as shown above and tap Activate.'],
            ]
            : [
                ["Open {$name}", "Launch {$name} on the device you want to license."],
                ['Open the license screen', 'Go to the license or account settings of the app.'],
                ['Paste the key and activate', 'Paste the license key exactly as shown above and confirm.'],
            ];

        return [
            'steps' => $steps,
            'notes' => [
                'To move the license to another device, release it in the app first (or ask us to release it), then activate it on the new device.',
                'After activation the app works offline for up to 30 days; connect to the internet now and then so the license can refresh.',
            ],
        ];
    }

    private function productIcon(?LicenseProduct $product): ?string
    {
        if (! $product?->icon_path) {
            return null;
        }

        $disk = Storage::disk(LicenseProduct::ICON_DISK);
        if (! $disk->exists($product->icon_path)) {
            return null;
        }

        return self::dataUri($disk->get($product->icon_path), $product->icon_path);
    }

    /**
     * The first that exists of resources/license-products/
     * {name}-{product code}-{variant}-{plan}.png, {name}-{variant}-{plan}.png,
     * {name}-{product code}-{variant}.png and {name}-{variant}.png. The variant
     * (a / b / c) comes from config('licensing.certificate_hologram').
     */
    private static function asset(string $name, ?LicenseProduct $product, ?string $plan = null): string
    {
        $variant = strtolower((string) config('licensing.certificate_hologram', 'b'));
        if (! in_array($variant, ['a', 'b', 'c'], true)) {
            $variant = 'b';
        }

        $candidates = [];
        if ($plan) {
            if ($product) {
                $candidates[] = "{$name}-{$product->code}-{$variant}-{$plan}";
            }
            $candidates[] = "{$name}-{$variant}-{$plan}";
        }
        if ($product) {
            $candidates[] = "{$name}-{$product->code}-{$variant}";
        }
        foreach ($candidates as $candidate) {
            $path = resource_path("license-products/{$candidate}.png");
            if (is_file($path)) {
                return $path;
            }
        }

        return resource_path("license-products/{$name}-{$variant}.png");
    }

    private static function embed(string $path): ?string
    {
        return is_file($path) ? self::dataUri(file_get_contents($path), $path) : null;
    }

    private static function dataUri(string $bytes, string $name): string
    {
        $mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
