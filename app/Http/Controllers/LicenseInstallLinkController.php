<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\LicenseInstallLinkResource;
use App\Models\License;
use App\Models\LicenseEvent;
use App\Models\LicenseInstallLink;
use App\Models\LicenseProduct;
use App\Models\LicenseRelease;
use App\Services\LicenseEventLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Single-use over-the-air install links for the iPad installer (.ipa) of
 * a version (back office). One link installs the app on one iPad; for
 * another iPad the admin makes a new link. Permissions reuse the version
 * module: reading needs license-release-menu / -list / -view, making and
 * revoking a link -edit (the same permission that uploads the installer).
 */
class LicenseInstallLinkController extends Controller implements HasMiddleware
{
    public function __construct(private LicenseEventLogger $events) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['license-release-menu|license-release-list|license-release-view']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['license-release-edit']), only: ['store', 'revoke']),
        ];
    }

    public function index(string $product, string $release)
    {
        [, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $links = $item->installLinks()->with(['release.product', 'license', 'creator', 'revoker'])->latest('id')->get();

        return ResponseHelper::jsonResponse(true, 'Install Links Retrieved Successfully', [
            'app_url_https' => LicenseInstallLink::appUrlIsHttps(),
            'default_days' => (int) config('licensing.install_link_days', 7),
            'max_days' => (int) config('licensing.install_link_max_days', 30),
            'links' => LicenseInstallLinkResource::collection($links),
        ], 200);
    }

    /**
     * Body: license_id (optional, an iPad license of the product that is
     * on, not expired and covers the version), device_name, note,
     * expires_in_days (default 7). The answer carries the full URL once
     * more (it is also listed while the link is active).
     */
    public function store(Request $request, string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $max = (int) config('licensing.install_link_max_days', 30);
        $data = $request->validate([
            'license_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.$max],
        ]);
        if (! $item->hasIpa()) {
            throw ValidationException::withMessages(['release' => 'Upload the iPad installer (.ipa) of this version first.']);
        }
        $license = $this->license($model, $item, $data['license_id'] ?? null);

        [$token, $tokenAttributes] = LicenseInstallLink::newToken();
        $link = $item->installLinks()->create(array_merge($tokenAttributes, [
            'license_id' => $license?->id,
            'device_name' => filled($data['device_name'] ?? null) ? trim($data['device_name']) : null,
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'created_by' => $request->user()?->id,
            'expires_at' => now()->addDays($data['expires_in_days'] ?? (int) config('licensing.install_link_days', 7)),
            'status' => LicenseInstallLink::STATUS_ACTIVE,
        ]));

        $meta = ['version' => $item->version, 'link_id' => $link->id, 'expires_at' => $link->expires_at->toIso8601ZuluString(), 'device_name' => $link->device_name];
        if ($license) {
            $this->events->log($license, LicenseEvent::INSTALL_LINK_CREATED, ['meta' => array_filter($meta)]);
        }
        activity('License Manager')->performedOn($link)->withProperties(array_filter($meta + ['license_id' => $license?->id]))->log('iPad install link created');

        $link->load(['release.product', 'license', 'creator']);

        return ResponseHelper::jsonResponse(true, 'Install Link Created Successfully', array_merge(
            (new LicenseInstallLinkResource($link))->resolve($request),
            [
                'url' => LicenseInstallLink::url($model->code, $token),
                'manifest_url' => LicenseInstallLink::url($model->code, $token, '/manifest.plist'),
                'app_url_https' => LicenseInstallLink::appUrlIsHttps(),
            ],
        ), 201);
    }

    public function revoke(Request $request, string $product, string $release, string $link)
    {
        [, $item] = $this->find($product, $release);
        $row = $item?->installLinks()->find($link);
        if (! $row) {
            return $this->notFound();
        }
        if ($row->status === LicenseInstallLink::STATUS_USED) {
            throw ValidationException::withMessages(['link' => 'This link has already been used.']);
        }
        // Only a usable link changes; an expired one simply stays expired.
        if ($row->isUsable()) {
            $row->update([
                'status' => LicenseInstallLink::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $request->user()?->id,
                'download_lease_id' => null,
                'download_lease_until' => null,
            ]);
            $meta = ['version' => $item->version, 'link_id' => $row->id];
            if ($row->license_id) {
                $this->events->log($row->license_id, LicenseEvent::INSTALL_LINK_REVOKED, ['meta' => $meta]);
            }
            activity('License Manager')->performedOn($row)->withProperties($meta)->log('iPad install link revoked');
        }
        $row->load(['release.product', 'license', 'creator', 'revoker']);

        return ResponseHelper::jsonResponse(true, 'Install Link Revoked Successfully', new LicenseInstallLinkResource($row), 200);
    }

    /**
     * The license a link is made for: an iPad license of this product that
     * is on, not expired and covers the version.
     */
    private function license(LicenseProduct $product, LicenseRelease $release, ?int $id): ?License
    {
        if ($id === null) {
            return null;
        }
        $license = License::forProduct($product)->find($id);
        $fail = fn (string $message) => throw ValidationException::withMessages(['license_id' => $message]);

        if (! $license) {
            $fail('This license does not exist for this product.');
        }
        if ($license->platform !== License::PLATFORM_IPAD) {
            $fail('Choose an iPad license.');
        }
        if (! $license->is_active) {
            $fail('This license is switched off.');
        }
        if ($license->isExpired()) {
            $fail('This license has expired.');
        }
        if (! $license->coversVersion($release->version)) {
            $fail("This license covers {$product->name} {$license->updates_major}.x, not {$release->version}.");
        }

        return $license;
    }

    /**
     * @return array{0: ?LicenseProduct, 1: ?LicenseRelease}
     */
    private function find(string $product, string $release): array
    {
        $model = LicenseProduct::findByCode($product);

        return [$model, $model?->releases()->find($release)];
    }

    private function notFound()
    {
        return ResponseHelper::jsonResponse(false, 'Not Found', null, 404);
    }
}
