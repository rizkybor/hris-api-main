<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use Illuminate\Support\Facades\DB;

/**
 * Device activation flow behind the public /api/licenses/{product}
 * endpoints (and the /api/occa/license alias). Keys are looked up only
 * within the product of the route.
 */
class LicenseService
{
    /**
     * Denied checks that are tracked per license (the key exists).
     */
    private const DENIAL_EVENTS = [
        'device_limit' => LicenseEvent::DENIED_DEVICE_LIMIT,
        'inactive' => LicenseEvent::DENIED_INACTIVE,
        'expired' => LicenseEvent::DENIED_EXPIRED,
        'wrong_platform' => LicenseEvent::DENIED_WRONG_PLATFORM,
    ];

    public function __construct(
        private LicenseTokenService $tokenService,
        private LicenseEventLogger $eventLogger
    ) {}

    /**
     * Register (or re-register) a device and return the signed response.
     * Re-activating a known device_id never consumes another slot.
     */
    public function activate(string $productCode, string $key, array $device): array
    {
        $product = $this->usableProduct($productCode);
        $deviceId = $device['device_id'];

        try {
            [$license, $activation, $isNew, $previousVersion] = $this->activateInTransaction($product, $key, $device, $deviceId);
        } catch (LicenseException $e) {
            $this->logDenial($e, $product, $key, $device);
            throw $e;
        }

        // Logged after commit so a logging problem can never roll back (or
        // block) the activation itself.
        $this->eventLogger->logDevice(
            $license,
            $isNew ? LicenseEvent::DEVICE_ACTIVATED : LicenseEvent::DEVICE_REACTIVATED,
            $activation
        );

        if (! $isNew) {
            $this->logAppUpdate($license, $activation, $previousVersion);
        }

        return $this->response($license, $activation->device_id);
    }

    /**
     * "app_updated" when a device reports another app version than before
     * (the version history of each device in the back office).
     */
    private function logAppUpdate(License $license, LicenseActivation $activation, ?string $previousVersion): void
    {
        $current = $activation->app_version;
        if ($previousVersion === null || $current === null || $previousVersion === $current) {
            return;
        }

        $this->eventLogger->logDevice($license, LicenseEvent::APP_UPDATED, $activation, [
            'from' => $previousVersion,
            'to' => $current,
        ]);
    }

    private function activateInTransaction(LicenseProduct $product, string $key, array $device, string $deviceId): array
    {
        return DB::transaction(function () use ($product, $key, $device, $deviceId) {
            // Lock the license row so two devices activating at the same
            // moment cannot both squeeze into the last free slot.
            $license = $this->findUsable($product, $key, lock: true);
            $this->assertPlatform($license, $device['platform'] ?? null, required: true);
            $now = now();

            $activation = $license->activations()->where('device_id', $deviceId)->first();
            $isNew = $activation === null;

            $previousVersion = $activation?->app_version;
            if ($activation) {
                $activation->update(array_merge(
                    ['last_seen_at' => $now],
                    $this->deviceDetails($device)
                ));
            } else {
                if ($license->activations()->count() >= $license->max_devices) {
                    throw LicenseException::deviceLimit($license->max_devices);
                }

                $activation = $license->activations()->create(array_merge(
                    $this->deviceDetails($device),
                    [
                        'device_id' => $deviceId,
                        'activated_at' => $now,
                        'last_seen_at' => $now,
                    ]
                ));

                activity('License Manager')
                    ->performedOn($license)
                    ->withProperties([
                        'device_id' => $deviceId,
                        'device_name' => $activation->device_name,
                        'platform' => $activation->platform,
                    ])
                    ->log('Device activated');
            }

            $license->forceFill(['last_activated_at' => $now])->saveQuietly();

            return [$license, $activation, $isNew, $previousVersion];
        });
    }

    /**
     * Re-issue a token for an already activated device.
     */
    public function refresh(string $productCode, string $key, string $deviceId, ?string $platform = null, ?string $appVersion = null): array
    {
        $product = $this->usableProduct($productCode);

        try {
            $license = $this->findUsable($product, $key);
            $activation = $license->activations()->where('device_id', $deviceId)->first();
            if (! $activation) {
                throw LicenseException::notActivated();
            }
            // The platform sent now, else the one the device activated with.
            $this->assertPlatform($license, $platform ?: $activation->platform, required: false);
        } catch (LicenseException $e) {
            $this->logDenial($e, $product, $key, ['device_id' => $deviceId, 'platform' => $platform]);
            throw $e;
        }

        // last_seen_at moves on every refresh; the event is throttled. A new
        // app version is stored and logged as "app_updated" (from -> to).
        $previousVersion = $activation->app_version;
        $activation->update(array_filter([
            'last_seen_at' => now(),
            'app_version' => $appVersion,
        ], fn ($value) => $value !== null && $value !== ''));
        $this->logAppUpdate($license, $activation, $previousVersion);
        $this->eventLogger->logRefresh($license, $activation);

        return $this->response($license, $deviceId);
    }

    /**
     * Free this device's slot. Idempotent: unknown keys/devices are fine.
     */
    public function deactivate(string $productCode, string $key, string $deviceId): void
    {
        // Freeing a slot always works, also for a product that is switched off.
        $product = LicenseProduct::findByCode($productCode);
        $license = $product ? $this->findByKey($product, $key) : null;
        if (! $license) {
            return;
        }

        $activation = $license->activations()->where('device_id', $deviceId)->first();
        if (! $activation) {
            return;
        }

        $activation->delete();

        activity('License Manager')
            ->performedOn($license)
            ->withProperties(['device_id' => $deviceId])
            ->log('Device deactivated');

        $this->eventLogger->logDevice($license, LicenseEvent::DEVICE_RELEASED, $activation);
    }

    /**
     * Track a denied activate / refresh when the key exists (an unknown key
     * has no license to attach the event to).
     */
    private function logDenial(LicenseException $e, LicenseProduct $product, string $key, array $device): void
    {
        $event = self::DENIAL_EVENTS[$e->errorCode] ?? null;
        if ($event === null) {
            return;
        }

        try {
            $license = $this->findByKey($product, $key);
            if (! $license) {
                return;
            }

            // A refresh only sends device_id; take the rest from the
            // device's activation when there is one.
            $known = $license->activations()->where('device_id', $device['device_id'] ?? null)->first();
            if ($known) {
                $device = array_merge($known->only(['device_name', 'platform', 'app_version']), array_filter($device, fn ($v) => $v !== null && $v !== ''));
            }
        } catch (\Throwable $lookupFailure) {
            report($lookupFailure);

            return;
        }

        $this->eventLogger->logDevice($license, $event, $device);
    }

    /**
     * The product of the route: unknown answers invalid_product, switched
     * off answers product_inactive.
     */
    private function usableProduct(string $code): LicenseProduct
    {
        $product = LicenseProduct::findByCode($code);
        if (! $product) {
            throw LicenseException::invalidProduct();
        }
        if (! $product->is_active) {
            throw LicenseException::productInactive();
        }

        return $product;
    }

    public function findByKey(LicenseProduct $product, string $key, bool $lock = false): ?License
    {
        $normalized = License::normalizeKey($key);
        if ($normalized === '') {
            return null;
        }

        return License::query()
            ->forProduct($product)
            ->where('key', $normalized)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /**
     * Find the license and run the checks shared by activate and refresh.
     */
    private function findUsable(LicenseProduct $product, string $key, bool $lock = false): License
    {
        $license = $this->findByKey($product, $key, $lock);

        if (! $license) {
            throw LicenseException::invalidKey();
        }
        $license->setRelation('product', $product);
        if (! $license->is_active) {
            throw LicenseException::inactive();
        }
        if ($license->isExpired()) {
            throw LicenseException::expired();
        }

        return $license;
    }

    /**
     * A Mac key only works on a Mac and an iPad key only on an iPad.
     * Activation must say which platform the device is; on refresh an
     * unknown platform (older activation without one) is let through.
     */
    private function assertPlatform(License $license, ?string $reported, bool $required): void
    {
        $platform = License::platformFromDevice($reported);

        if ($platform === null) {
            if ($required) {
                throw LicenseException::unknownPlatform();
            }

            return;
        }

        if ($platform !== $license->platform) {
            throw LicenseException::wrongPlatform($license->platform);
        }
    }

    /**
     * Only overwrite optional device details the app actually sent.
     */
    private function deviceDetails(array $device): array
    {
        return array_filter([
            'device_name' => $device['device_name'] ?? null,
            'platform' => $device['platform'] ?? null,
            'app_version' => $device['app_version'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function response(License $license, string $deviceId): array
    {
        return [
            'token' => $this->tokenService->issue($license, $deviceId),
            'license' => [
                'product' => $license->product?->code,
                'key' => $license->key,
                'plan' => $license->plan,
                'platform' => $license->platform,
                'type' => $license->type,
                'licensee' => $license->licensee_name,
                'expires_at' => LicenseTokenService::isoUtc($license),
                'max_devices' => $license->max_devices,
                'devices_used' => $license->activations()->count(),
            ],
        ];
    }
}
