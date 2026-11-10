<?php

namespace App\Http\Controllers;

use App\Exceptions\LicenseException;
use App\Models\License;
use App\Models\LicenseRelease;
use App\Services\LicenseReleaseService;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Public, unauthenticated endpoints an app calls to activate, refresh and
 * deactivate a license key on a device: /api/licenses/{product}/... with
 * the product code, and /api/occa/license/... as the OCCA alias (the path
 * compiled into the OCCA app). Errors use the
 * {"error": code, "message": text} shape the app understands rather than
 * the back-office {success, message, data} envelope.
 */
class LicensePublicController extends Controller
{
    public function __construct(
        private LicenseService $licenseService,
        private LicenseReleaseService $releaseService
    ) {}

    /**
     * Published version list (newest first), for "What's new" in the app.
     */
    public function releases(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, [
            'platform' => ['nullable', Rule::in(['mac', 'ipad', 'macOS', 'iPadOS'])],
        ], fn (array $data) => $this->releaseService->history($product, $this->platform($data['platform'] ?? null)));
    }

    /**
     * Update check for a running app: version (required), platform and,
     * when the device is activated, the license key (Lifetime covers one
     * major version).
     */
    public function latestRelease(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, [
            'version' => ['required', 'string', 'max:20'],
            'platform' => ['nullable', Rule::in(['mac', 'ipad', 'macOS', 'iPadOS'])],
            'key' => ['nullable', 'string', 'max:64'],
        ], fn (array $data) => $this->releaseService->check(
            $product,
            $this->platform($data['platform'] ?? null),
            $data['version'],
            $data['key'] ?? null
        ));
    }

    /**
     * Download a version's installer with a license key (?key=...).
     */
    public function download(Request $request, string $product, string $version)
    {
        $validator = Validator::make($request->all(), ['key' => ['required', 'string', 'max:64']]);
        if ($validator->fails()) {
            return $this->error('invalid_request', $validator->errors()->first(), 422);
        }

        try {
            [$release, $path] = $this->releaseService->installer($product, $version, $request->input('key'));
        } catch (LicenseException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->status);
        }

        return Storage::disk(LicenseRelease::FILE_DISK)->download($path, $release->file_name);
    }

    /**
     * "macOS" / "iPadOS" (what the apps send) or "mac" / "ipad".
     */
    private function platform(?string $value): ?string
    {
        return $value === null ? null : License::platformFromDevice($value);
    }

    public function activate(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, [
            'key' => ['required', 'string', 'max:64'],
            'device_id' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            // "macOS" or "iPadOS": a Mac key only activates on a Mac, an
            // iPad key only on an iPad.
            'platform' => ['required', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ], fn (array $data) => $this->licenseService->activate($product, $data['key'], $data));
    }

    public function refresh(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, $this->keyAndDeviceRules(),
            fn (array $data) => $this->licenseService->refresh($product, $data['key'], $data['device_id'], $data['platform'] ?? null, $data['app_version'] ?? null));
    }

    public function deactivate(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, $this->keyAndDeviceRules(), function (array $data) use ($product) {
            $this->licenseService->deactivate($product, $data['key'], $data['device_id']);

            return ['ok' => true];
        });
    }

    private function keyAndDeviceRules(): array
    {
        return [
            'key' => ['required', 'string', 'max:64'],
            'device_id' => ['required', 'string', 'max:255'],
            // Optional on refresh / deactivate; checked when sent.
            'platform' => ['nullable', 'string', 'max:50'],
            // Optional: the running app version (a change is logged as "app_updated").
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function handle(Request $request, array $rules, callable $action): JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->error('invalid_request', $validator->errors()->first(), 422);
        }

        try {
            return response()->json($action($validator->validated()), 200);
        } catch (LicenseException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->status);
        } catch (\Throwable $e) {
            Log::error('License endpoint failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->error('server_error', 'The license server could not process the request. Please try again later.', 500);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
