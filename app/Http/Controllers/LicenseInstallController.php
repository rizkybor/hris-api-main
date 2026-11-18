<?php

namespace App\Http\Controllers;

use App\Models\LicenseEvent;
use App\Models\LicenseInstallLink;
use App\Models\LicenseProduct;
use App\Models\LicenseRelease;
use App\Services\LicenseEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public over-the-air install of an iPad version through a single-use
 * link (no login; the token is the only credential):
 *
 *   GET /api/licenses/{product}/install/{token}                 page with the Install button
 *   GET /api/licenses/{product}/install/{token}/manifest.plist  iOS OTA manifest (may be fetched again)
 *   GET /api/licenses/{product}/install/{token}/app.ipa         the .ipa; the link is used once it streamed completely
 *
 * Used, expired and revoked links answer 410. Range requests are not
 * supported: the .ipa is always sent whole (Accept-Ranges: none), so
 * "complete" is unambiguous. Only one download of a link runs at a time.
 */
class LicenseInstallController extends Controller
{
    private const CHUNK_BYTES = 1024 * 1024;

    private const RENEW_EVERY_SECONDS = 10;

    public function __construct(private LicenseEventLogger $events) {}

    public function page(Request $request, string $product, string $token)
    {
        $lang = $this->language($request);
        [$model, $link] = $this->find($product, $token);
        if (! $link) {
            return $this->pageResponse($lang, null, null, 'not_found', 404);
        }
        $release = $link->release;
        $status = $link->status();
        if ($status === LicenseInstallLink::STATUS_ACTIVE && ! $release?->hasIpa()) {
            $status = 'unavailable';
        }
        if ($status !== LicenseInstallLink::STATUS_ACTIVE) {
            return $this->pageResponse($lang, $model, $link, $status, 410);
        }
        if ($link->page_opened_at === null) {
            $link->forceFill(['page_opened_at' => now()])->saveQuietly();
        }

        return $this->pageResponse($lang, $model, $link, $status, 200, [
            'itms_url' => 'itms-services://?action=download-manifest&url='.rawurlencode(LicenseInstallLink::url($model->code, $token, '/manifest.plist')),
        ]);
    }

    /**
     * The OTA manifest iPadOS reads after "Install": where the .ipa is and
     * which app it is. text/xml, as Apple's in-house distribution guide asks.
     */
    public function manifest(string $product, string $token)
    {
        [$model, $link] = $this->find($product, $token);
        if (! $link) {
            return $this->plain('This install link does not exist.', 404);
        }
        if (! $link->isUsable()) {
            return $this->gone($link);
        }
        $release = $link->release;
        if (! $release?->hasIpa()) {
            return $this->plain('This version has no iPad installer any more.', 410);
        }
        $link->forceFill(['manifest_fetched_at' => now()])->saveQuietly();

        $bundleId = $release->ipa_meta['bundle_id'] ?? config("licensing.ios_bundle_ids.{$model->code}");
        $xml = self::manifestXml(
            LicenseInstallLink::url($model->code, $token, '/app.ipa'),
            (string) $bundleId,
            (string) ($release->ipa_meta['short_version'] ?? $release->version),
            $model->name,
        );

        return response($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Streams the .ipa. The link becomes "used" only when every byte was
     * handed to the web server without the client going away; an
     * interrupted download releases the link at once, so iPadOS (or the
     * user) can retry. A HEAD request answers the headers only and never
     * uses the link.
     */
    public function ipa(Request $request, string $product, string $token)
    {
        [, $link] = $this->find($product, $token);
        if (! $link) {
            return $this->plain('This install link does not exist.', 404);
        }
        if (! $link->isUsable()) {
            return $this->gone($link);
        }
        $release = $link->release;
        $disk = Storage::disk(LicenseRelease::FILE_DISK);
        if (! $release?->hasIpa() || ! $disk->exists($release->ipa_path)) {
            return $this->plain('This version has no iPad installer any more.', 410);
        }
        $size = (int) $disk->size($release->ipa_path);
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'attachment; filename="'.$release->ipa_name.'"',
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'no-store',
            // nginx: pass the bytes straight through, so "sent" means sent to the iPad.
            'X-Accel-Buffering' => 'no',
        ];
        if ($request->isMethod('HEAD')) {
            return response('', 200, $headers);
        }

        $leaseId = $link->claimDownload();
        if ($leaseId === null) {
            $link->refresh();

            return $link->isUsable()
                ? $this->plain('This link is being downloaded right now. Try again in a minute.', 409)
                : $this->gone($link);
        }

        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $path = $release->ipa_path;

        return new StreamedResponse(function () use ($link, $leaseId, $disk, $path, $size, $ip, $userAgent, $release) {
            ignore_user_abort(true);
            @set_time_limit(0);
            $sent = 0;
            $complete = false;
            $stream = $disk->readStream($path);
            try {
                $lastRenew = time();
                while ($stream && ! feof($stream)) {
                    $chunk = fread($stream, self::CHUNK_BYTES);
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    flush();
                    $sent += strlen($chunk);
                    if (connection_aborted()) {
                        break;
                    }
                    if (time() - $lastRenew >= self::RENEW_EVERY_SECONDS) {
                        $link->renewDownload($leaseId);
                        $lastRenew = time();
                    }
                }
                $complete = $sent === $size && ! connection_aborted();
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($complete && $link->markUsed($leaseId, $ip, $userAgent)) {
                    $this->logUsed($link, $release, $ip, $userAgent, $size);
                } elseif (! $complete) {
                    $link->releaseDownload($leaseId);
                }
            }
        }, 200, $headers);
    }

    /**
     * items[0]: assets [software-package = the .ipa URL] and metadata
     * (bundle-identifier, bundle-version, kind, title).
     */
    public static function manifestXml(string $ipaUrl, string $bundleId, string $bundleVersion, string $title): string
    {
        $e = fn (string $value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
            <plist version="1.0">
            <dict>
                <key>items</key>
                <array>
                    <dict>
                        <key>assets</key>
                        <array>
                            <dict>
                                <key>kind</key>
                                <string>software-package</string>
                                <key>url</key>
                                <string>{$e($ipaUrl)}</string>
                            </dict>
                        </array>
                        <key>metadata</key>
                        <dict>
                            <key>bundle-identifier</key>
                            <string>{$e($bundleId)}</string>
                            <key>bundle-version</key>
                            <string>{$e($bundleVersion)}</string>
                            <key>kind</key>
                            <string>software</string>
                            <key>title</key>
                            <string>{$e($title)}</string>
                        </dict>
                    </dict>
                </array>
            </dict>
            </plist>

            XML;
    }

    private function logUsed(LicenseInstallLink $link, LicenseRelease $release, ?string $ip, ?string $userAgent, int $size): void
    {
        $meta = array_filter([
            'version' => $release->version,
            'link_id' => $link->id,
            'device_name' => $link->device_name,
            'bytes' => $size,
            'user_agent' => $userAgent,
        ]);
        if ($link->license_id) {
            $this->events->log($link->license_id, LicenseEvent::INSTALL_LINK_USED, [
                'device_name' => $link->device_name,
                'platform' => 'iPadOS',
                'app_version' => $release->version,
                'ip' => $ip,
                'user_id' => null,
                'meta' => $meta,
            ]);
        }
        try {
            activity('License Manager')->performedOn($link)->withProperties($meta + ['ip' => $ip, 'license_id' => $link->license_id])->log('iPad app installed from an install link');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{0: ?LicenseProduct, 1: ?LicenseInstallLink}
     */
    private function find(string $product, string $token): array
    {
        $model = LicenseProduct::findByCode($product);
        if (! $model) {
            return [null, null];
        }
        $link = LicenseInstallLink::findByToken($token);
        if (! $link || $link->release?->product_id !== $model->id) {
            return [$model, null];
        }

        return [$model, $link];
    }

    private function gone(LicenseInstallLink $link): Response
    {
        return $this->plain(match ($link->status()) {
            LicenseInstallLink::STATUS_USED => 'This install link has already been used. Ask for a new link.',
            LicenseInstallLink::STATUS_REVOKED => 'This install link has been revoked. Ask for a new link.',
            default => 'This install link has expired. Ask for a new link.',
        }, 410);
    }

    private function plain(string $message, int $status): Response
    {
        return response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function pageResponse(string $lang, ?LicenseProduct $product, ?LicenseInstallLink $link, string $status, int $code, array $extra = []): Response
    {
        return response()->view('licenses.install-page', array_merge([
            'lang' => $lang,
            'status' => $status,
            'productName' => $product?->name ?? 'App',
            'icon' => $this->icon($product),
            'version' => $link?->release?->version,
            'expiresAt' => $link?->expires_at?->timezone('Asia/Jakarta'),
            'usedAt' => $link?->used_at?->timezone('Asia/Jakarta'),
            'itms_url' => null,
        ], $extra), $code, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    /**
     * The product icon inlined (data URI), so the page needs no other URL.
     */
    private function icon(?LicenseProduct $product): ?string
    {
        try {
            $disk = Storage::disk(LicenseProduct::ICON_DISK);
            if (! $product?->icon_path || ! $disk->exists($product->icon_path) || $disk->size($product->icon_path) > 1024 * 1024) {
                return null;
            }
            $mime = $disk->mimeType($product->icon_path) ?: 'image/png';

            return "data:{$mime};base64,".base64_encode($disk->get($product->icon_path));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ?lang=id|en, else the browser's language (Indonesian when it prefers it).
     */
    private function language(Request $request): string
    {
        $lang = $request->query('lang');
        if (in_array($lang, ['id', 'en'], true)) {
            return $lang;
        }

        return str_starts_with(strtolower((string) $request->getPreferredLanguage(['en', 'id'])), 'id') ? 'id' : 'en';
    }
}
