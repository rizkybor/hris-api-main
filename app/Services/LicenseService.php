<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use App\Models\LicenseKey;
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
        'key_used' => LicenseEvent::DENIED_KEY_USED,
        'key_in_use' => LicenseEvent::DENIED_KEY_IN_USE,
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
            [$license, $activation, $isNew, $previousVersion, $usedKey] = $this->activateInTransaction($product, $key, $device, $deviceId);
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

        return $this->response($license, $activation->device_id, $usedKey);
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
            // Lock the license row (and the key) so two devices activating at
            // the same moment cannot both squeeze into the last free slot or
            // both take the same per-device key.
            [$license, $keyRow] = $this->findUsable($product, $key, lock: true);
            $this->assertPlatform($license, $device['platform'] ?? null, required: true);
            $now = now();

            $activation = $license->activations()->where('device_id', $deviceId)->first();
            $isNew = $activation === null;

            // "One key per device": a key works on exactly one device, and a
            // released one never again.
            if ($keyRow) {
                if ($keyRow->isUsed()) {
                    throw LicenseException::keyUsed();
                }
                if ($keyRow->isInUse() && $keyRow->device_id !== $deviceId) {
                    throw LicenseException::keyInUse();
                }
            }

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
                        'license_key_id' => $keyRow?->id,
                        'device_id' => $deviceId,
                        'activated_at' => $now,
                        'last_seen_at' => $now,
                    ]
                ));

                if ($keyRow) {
                    $keyRow->forceFill([
                        'status' => LicenseKey::STATUS_IN_USE,
                        'device_id' => $deviceId,
                        'device_name' => $activation->device_name,
                        'platform' => $activation->platform,
                        'activated_at' => $now,
                    ])->save();
                }

                activity('License Manager')
                    ->performedOn($license)
                    ->withProperties(array_filter([
                        'device_id' => $deviceId,
                        'device_name' => $activation->device_name,
                        'platform' => $activation->platform,
                        'key' => $keyRow?->key,
                    ]))
                    ->log('Device activated');
            }

            $license->forceFill(['last_activated_at' => $now])->saveQuietly();

            // The key this device holds: its own per-device key (a device that
            // is already on the license keeps its key and frees no other).
            $usedKey = $activation->license_key_id
                ? (LicenseKey::find($activation->license_key_id)?->key ?? $license->key)
                : ($keyRow?->key ?? $license->key);

            return [$license, $activation, $isNew, $previousVersion, $usedKey];
        });
    }

    /**
     * Re-issue a token for an already activated device.
     */
    public function refresh(string $productCode, string $key, string $deviceId, ?string $platform = null, ?string $appVersion = null): array
    {
        $product = $this->usableProduct($productCode);

        try {
            [$license, $keyRow] = $this->findUsable($product, $key);
            $activation = $license->activations()->where('device_id', $deviceId)->first();
            if (! $activation) {
                throw $keyRow?->isUsed() ? LicenseException::keyUsed() : LicenseException::notActivated();
            }
            // A per-device key only refreshes the device it is bound to.
            if ($keyRow && $activation->license_key_id && $activation->license_key_id !== $keyRow->id) {
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

        return $this->response($license, $deviceId, $keyRow?->key ?? $license->key);
    }

    /**
     * Free this device's slot. Idempotent: unknown keys/devices are fine.
     */
    public function deactivate(string $productCode, string $key, string $deviceId): void
    {
        // Freeing a slot always works, also for a product that is switched off.
        $product = LicenseProduct::findByCode($productCode);
        [$license, $keyRow] = $product ? $this->resolveKey($product, $key) : [null, null];
        if (! $license) {
            return;
        }

        $activation = $license->activations()->where('device_id', $deviceId)->first();
        if (! $activation) {
            return;
        }
        // A per-device key only releases the device it is bound to.
        if ($keyRow && $activation->license_key_id && $activation->license_key_id !== $keyRow->id) {
            return;
        }

        $activation->delete();
        // Released: a per-device key never works again.
        $activation->licenseKey?->markUsed();

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
        return $this->resolveKey($product, $key, $lock)[0];
    }

    /**
     * [license, per-device key or null] for a key within the product: a
     * "one key per device" license matches any of its keys, a shared one
     * its own key.
     *
     * @return array{0: ?License, 1: ?LicenseKey}
     */
    public function resolveKey(LicenseProduct $product, string $key, bool $lock = false): array
    {
        $normalized = License::normalizeKey($key);
        if ($normalized === '') {
            return [null, null];
        }

        $keyRow = LicenseKey::query()->where('key', $normalized)->first();
        if ($keyRow) {
            $license = License::query()->forProduct($product)->whereKey($keyRow->license_id)
                ->when($lock, fn ($query) => $query->lockForUpdate())->first();
            if ($license && $license->isPerDevice()) {
                return [$license, $lock ? LicenseKey::query()->lockForUpdate()->find($keyRow->id) : $keyRow];
            }
        }

        $license = License::query()
            ->forProduct($product)
            ->where('key', $normalized)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        return [$license, null];
    }

    /**
     * Find the license and run the checks shared by activate and refresh.
     */
    /**
     * @return array{0: License, 1: ?LicenseKey}
     */
    private function findUsable(LicenseProduct $product, string $key, bool $lock = false): array
    {
        [$license, $keyRow] = $this->resolveKey($product, $key, $lock);

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

        return [$license, $keyRow];
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

    /**
     * $key: the key the device holds (its per-device key, or the license's
     * shared key); it goes into the signed token as well.
     */
    private function response(License $license, string $deviceId, ?string $key = null): array
    {
        $key ??= $license->key;
        $perDevice = $license->isPerDevice();

        return [
            'token' => $this->tokenService->issue($license, $deviceId, null, $key),
            'license' => [
                'product' => $license->product?->code,
                'key' => $key,
                'plan' => $license->plan,
                'platform' => $license->platform,
                'type' => $license->type,
                'licensee' => $license->licensee_name,
                'expires_at' => LicenseTokenService::isoUtc($license),
                'max_devices' => $license->max_devices,
                'devices_used' => $license->activations()->count(),
                // shared: one key for every device; per_device: one key each,
                // and a released key can never be used again (the app warns
                // before releasing).
                'key_mode' => $license->key_mode ?? License::KEY_MODE_SHARED,
                // per_device only: this key's number and how many there are.
                'key_position' => $perDevice ? $license->keys()->where('key', $key)->value('position') : null,
                'keys_total' => $perDevice ? $license->keys()->count() : null,
            ],
        ];
    }
}
