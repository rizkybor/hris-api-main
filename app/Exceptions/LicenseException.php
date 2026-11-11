<?php

namespace App\Exceptions;

use App\Models\License;
use RuntimeException;

/**
 * A license check the app is expected to handle, rendered as
 * {"error": code, "message": text} with the given HTTP status.
 */
class LicenseException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status
    ) {
        parent::__construct($message);
    }

    public static function invalidProduct(): self
    {
        return new self('invalid_product', 'This product does not exist.', 404);
    }

    public static function productInactive(): self
    {
        return new self('product_inactive', 'This product is not available for activation.', 403);
    }

    public static function invalidKey(): self
    {
        return new self('invalid_key', 'This license key does not exist.', 404);
    }

    public static function inactive(): self
    {
        return new self('inactive', 'This license key has been deactivated.', 403);
    }

    public static function expired(): self
    {
        return new self('expired', 'This license key has expired.', 403);
    }

    public static function deviceLimit(int $maxDevices): self
    {
        return new self('device_limit', "This license key is already in use on {$maxDevices} device(s). Deactivate another device first.", 409);
    }

    /**
     * A Mac key on an iPad or the other way round.
     */
    public static function wrongPlatform(string $licensePlatform): self
    {
        $label = License::PLATFORM_LABELS[$licensePlatform] ?? $licensePlatform;

        return new self('wrong_platform', "This license key is for {$label} and cannot be used on this device.", 403);
    }

    /**
     * The app did not say (or said something unknown) which platform it
     * runs on; activation needs "macOS" or "iPadOS".
     */
    public static function unknownPlatform(): self
    {
        return new self('invalid_request', 'The platform must be macOS or iPadOS.', 422);
    }

    /**
     * "One key per device": this key was used on a device that has since
     * been released, so it can never be activated again.
     */
    public static function keyUsed(): self
    {
        return new self('key_used', 'This license key has already been used and cannot be activated again.', 403);
    }

    /** "One key per device": the key is activated on another device. */
    public static function keyInUse(): self
    {
        return new self('key_in_use', 'This license key is already activated on another device.', 409);
    }

    /** The key was replaced by a new one when the license changed plan. */
    public static function keyReplaced(): self
    {
        return new self('key_replaced', 'This license key was replaced after a plan change. Use the new key from your license certificate.', 403);
    }

    public static function notActivated(): self
    {
        return new self('not_activated', 'This device is not activated for this license key.', 404);
    }
}
