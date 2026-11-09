<?php

namespace App\Exceptions;

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

    public static function notActivated(): self
    {
        return new self('not_activated', 'This device is not activated for this license key.', 404);
    }
}
