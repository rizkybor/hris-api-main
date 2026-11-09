<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Models\License;
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
            [$license, $activation, $isNew] = $this->activateInTransaction($product, $key, $device, $deviceId);
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

        return $this->response($license, $activation->device_id);
    }

    private function activateInTransaction(LicenseProduct $product, string $key, array $device, string $deviceId): array
    {
        return DB::transaction(function () use ($product, $key, $device, $deviceId) {
            // Lock the license row so two devices activating at the same
            // moment cannot both squeeze into the last free slot.
            $license = $this->findUsable($product, $key, lock: true);
            $now = now();

            $activation = $license->activations()->where('device_id', $deviceId)->first();
            $isNew = $activation === null;

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

            return [$license, $activation, $isNew];
        });
    }

    /**
     * Re-issue a token for an already activated device.
     */
    public function refresh(string $productCode, string $key, string $deviceId): array
    {
        $product = $this->usableProduct($productCode);

        try {
            $license = $this->findUsable($product, $key);
        } catch (LicenseException $e) {
            $this->logDenial($e, $product, $key, ['device_id' => $deviceId]);
            throw $e;
        }

        $activation = $license->activations()->where('device_id', $deviceId)->first();
        if (! $activation) {
            throw LicenseException::notActivated();
        }

        // last_seen_at moves on every refresh; the event is throttled.
        $activation->update(['last_seen_at' => now()]);
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
                'type' => $license->type,
                'licensee' => $license->licensee_name,
                'expires_at' => LicenseTokenService::isoUtc($license),
                'max_devices' => $license->max_devices,
                'devices_used' => $license->activations()->count(),
            ],
        ];
    }
}
