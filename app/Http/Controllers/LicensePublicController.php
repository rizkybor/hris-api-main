<?php

namespace App\Http\Controllers;

use App\Exceptions\LicenseException;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
    public function __construct(private LicenseService $licenseService) {}

    public function activate(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, [
            'key' => ['required', 'string', 'max:64'],
            'device_id' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ], fn (array $data) => $this->licenseService->activate($product, $data['key'], $data));
    }

    public function refresh(Request $request, string $product): JsonResponse
    {
        return $this->handle($request, $this->keyAndDeviceRules(),
            fn (array $data) => $this->licenseService->refresh($product, $data['key'], $data['device_id']));
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
