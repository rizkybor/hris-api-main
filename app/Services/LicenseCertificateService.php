<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseKey;
use App\Models\LicenseProduct;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /**
     * The whole license (every key; both licenses of a bundle), or with
     * $only a certificate for that one key of a "one key per device"
     * license, e.g. for the member who uses it.
     */
    public function render(License $license, ?LicenseKey $only = null): string
    {
        $pdf = Pdf::loadView('pdf.license-certificate', $this->data($license, $only))
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

    public function filename(License $license, ?LicenseKey $only = null): string
    {
        $keys = $this->bundleKeys($license);
        $product = $keys->first()->product ?? $license->product;
        $name = $only ? $only->key : $keys->pluck('key')->implode('_');

        return Str::slug($product?->name ?: 'License', '-').'-License-'.$name.'.pdf';
    }

    /**
     * A key of the license or of its bundle (Mac + iPad), or null.
     */
    public function findKey(License $license, int|string|null $keyId): ?LicenseKey
    {
        if (! $keyId) {
            return null;
        }

        return LicenseKey::query()
            ->whereKey($keyId)
            ->whereIn('license_id', $this->bundleKeys($license)->pluck('id'))
            ->first();
    }

    /**
     * LIC-OCCA-MAC-0001-K03: the reference of a one-key certificate.
     */
    public static function keyReference(LicenseKey $key): string
    {
        return self::reference($key->license).'-K'.str_pad((string) $key->position, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The licenses one certificate covers: the license itself, or for a
     * Mac + iPad bundle both of them, Mac first. A bundle has one
     * certificate, the same whichever of its licenses it is opened from.
     *
     * @return Collection<int, License>
     */
    public function bundleKeys(License $license): Collection
    {
        if (! $license->bundle_id) {
            return collect([$license]);
        }

        $order = array_flip(License::PLATFORMS);

        return License::query()
            ->with('product')
            ->where('bundle_id', $license->bundle_id)
            ->get()
            ->sortBy(fn (License $member) => $order[$member->platform] ?? 99)
            ->values();
    }

    /**
     * LIC-OCCA-MAC-0001, or LIC-OCCA-MAC-0001+IPAD-0001 for a bundle.
     */
    public static function certificateReference(Collection $keys): string
    {
        $first = self::reference($keys->first());

        return $keys->skip(1)->reduce(
            fn (string $reference, License $other) => $reference.'+'.strtoupper($other->platform).'-'.str_pad((string) ($other->sequence ?? $other->id), 4, '0', STR_PAD_LEFT),
            $first
        );
    }

    /**
     * LIC-OCCA-0001: product code and the license's number in the product.
     */
    /**
     * LIC-OCCA-MAC-0001 / LIC-OCCA-IPAD-0001: Mac and iPad number their
     * licenses separately, so the platform keeps references unique.
     */
    public static function reference(License $license): string
    {
        return 'LIC-'.strtoupper($license->product?->code ?: 'KEY')
            .'-'.strtoupper($license->platform ?: License::PLATFORM_MAC)
            .'-'.str_pad((string) ($license->sequence ?? $license->id), 4, '0', STR_PAD_LEFT);
    }

    public function data(License $license, ?LicenseKey $only = null): array
    {
        $license->loadMissing('product');
        // One key: the certificate covers only the license that holds it.
        if ($only) {
            $license = $only->license()->with('product')->first();
        }
        $keys = $only ? collect([$license]) : $this->bundleKeys($license);
        $isBundle = $keys->count() > 1;
        // Everything shared (licensee, plan, period) comes from the Mac key
        // of a bundle, so both of its licenses give the same certificate.
        $license = $keys->first();
        $license->loadMissing('product');
        $product = $license->product;
        $issuedAt = $license->created_at ?? now();
        $termsVersion = $license->terms_version ?: $product?->terms_version;
        $hasTerms = filled($product?->terms);

        return [
            'license' => $license,
            'keys' => $keys,
            'isBundle' => $isBundle,
            'platformsLabel' => $keys->map->platformLabel()->implode(' + '),
            // "One key per device": every key of each license, for the Device
            // Keys page (status as of the day the PDF is made).
            'perDevice' => $license->isPerDevice(),
            'deviceKeys' => $license->isPerDevice()
                ? $keys->map(fn (License $member) => ['license' => $member, 'keys' => $member->keys()->get()])->values()
                : collect(),
            'keysPerLicense' => $license->isPerDevice() ? $license->keys()->count() : null,
            // A certificate for one key only (no Device Keys page).
            'singleKey' => $only,
            'keysAsOf' => self::date(now()),
            'product' => $product,
            'accent' => self::ACCENT,
            'reference' => $only ? self::keyReference($only->setRelation('license', $license)) : self::certificateReference($keys),
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
            'steps' => $this->activationSteps($product, $license, $isBundle, $only !== null),
            'productIcon' => $this->productIcon($product),
            'companyLogo' => self::embed(resource_path('license-products/jcd-logo.png')),
            // Holographic artwork (php artisan license:certificate-assets),
            // in the configured variant; product-specific when available.
            // Key box and plan chip in the plan's colours (as in the app).
            'foil' => self::embed(self::asset('cert-foil', $product, $license->plan)),
            // The ring names the category (INDIVIDUAL / ORGANIZATION); a
            // Trial certificate has no seal.
            'seal' => $license->plan === License::PLAN_TRIAL
                ? null
                : self::embed(self::asset('cert-seal', $product, $license->category())),
            'chip' => self::embed(self::asset('cert-chip', null, $license->plan)),
            'planInk' => self::PLAN_INK[$license->plan] ?? '#14161F',
            'band' => self::embed(resource_path('license-products/certificate-band.png')),
            // Holographic ribbon across each page corner, on every page.
            'ribbons' => collect(['tl', 'tr', 'bl', 'br'])
                ->mapWithKeys(fn (string $corner) => [$corner => self::embed(self::asset('cert-ribbon', null, $corner))])
                ->filter()
                ->all(),
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
    private function activationSteps(?LicenseProduct $product, License $license, bool $isBundle = false, bool $singleKey = false): array
    {
        $name = $product?->name ?: 'the app';
        // A key only activates on its own platform: Mac (click, Starter
        // chip) or iPad (tap; there is no free plan on iPad).
        $ipad = $license->platform === License::PLATFORM_IPAD;
        $device = $ipad ? 'iPad' : 'Mac';
        $press = $ipad ? 'Tap' : 'Click';

        $bundleSteps = [
            ['Open OCCA', 'Launch OCCA on your Mac or iPad and go to the project list.'],
            ['Open License', 'Click (Mac) or tap (iPad) the plan chip next to the OCCA name at the top of the project list.'],
            ['Paste the key and activate', 'Use the Mac key on a Mac and the iPad key on an iPad; paste it exactly as shown above and activate.'],
        ];

        $steps = $product?->code === LicenseProduct::OCCA && $isBundle ? $bundleSteps : ($product?->code === LicenseProduct::OCCA
            ? [
                ['Open OCCA', "Launch OCCA on your {$device} and go to the project list. This key works on {$device} only."],
                ['Open License', $ipad
                    ? 'Tap the plan chip next to the OCCA name at the top of the project list.'
                    : 'Click the plan chip (Starter) next to the OCCA name at the top of the project list.'],
                ['Paste the key and activate', 'Paste the license key exactly as shown above and '.strtolower($press).' Activate.'],
            ]
            : [
                ["Open {$name}", "Launch {$name} on the device you want to license."],
                ['Open the license screen', 'Go to the license or account settings of the app.'],
                ['Paste the key and activate', 'Paste the license key exactly as shown above and confirm.'],
            ]);

        return [
            'steps' => $steps,
            'notes' => [
                match (true) {
                    $singleKey => 'A released key cannot be used again: to move to another device, ask the licensee for another key.',
                    $license->isPerDevice() => 'A released key cannot be used again: to move to another device, release the old one, then activate the new device with an available key.',
                    default => 'To move the license to another device, release it in the app first (or ask us to release it), then activate it on the new device.',
                },
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
