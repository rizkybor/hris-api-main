<?php

namespace App\Services;

use App\Models\License;
use RuntimeException;

/**
 * Signs the license tokens the apps (OCCA, ...) verify
 * offline. One keypair signs the tokens of every product; the payload's
 * "product" field tells the app which product a token is for.
 *
 * token     = base64url(payloadJSON) . "." . base64url(signature)
 * signature = Ed25519 detached signature over the ASCII base64url payload
 * base64url is unpadded.
 */
class LicenseTokenService
{
    public const VERSION = 1;

    /**
     * Build the signed token for one device of a license.
     */
    public function issue(License $license, string $deviceId, ?int $issuedAt = null): string
    {
        return $this->sign($this->payload($license, $deviceId, $issuedAt ?? time()));
    }

    public function payload(License $license, string $deviceId, int $issuedAt): array
    {
        $refreshAfterDays = (int) config('licensing.license_refresh_after_days', 7);
        $offlineDays = (int) config('licensing.license_offline_days', 30);

        $expiresAt = $license->expires_at;
        $offlineUntil = $issuedAt + $offlineDays * 86400;
        if ($expiresAt !== null) {
            $offlineUntil = min($offlineUntil, $expiresAt->getTimestamp());
        }

        return [
            'v' => self::VERSION,
            'product' => $license->product?->code,
            'lid' => (int) $license->id,
            'key' => $license->key,
            'plan' => $license->plan,
            'platform' => $license->platform,
            'type' => $license->type,
            // Lifetime: the app's major version must not be above this (null = every version).
            'updates_major' => $license->updates_major,
            'licensee' => $license->licensee_name,
            'expires_at' => self::isoUtc($license),
            'device_id' => $deviceId,
            'iat' => $issuedAt,
            'refresh_after' => $issuedAt + $refreshAfterDays * 86400,
            'offline_until' => $offlineUntil,
        ];
    }

    public function sign(array $payload): string
    {
        $encodedPayload = self::base64UrlEncode(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $signature = sodium_crypto_sign_detached($encodedPayload, $this->secretKey());

        return $encodedPayload.'.'.self::base64UrlEncode($signature);
    }

    /**
     * Verify a token with the configured public key and return its payload,
     * or null when the signature is invalid. Used by tests and diagnostics.
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $signature = self::base64UrlDecode($encodedSignature);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($signature, $encodedPayload, $this->publicKey())) {
            return null;
        }

        $json = self::base64UrlDecode($encodedPayload);

        return $json === false ? null : json_decode($json, true);
    }

    /**
     * ISO 8601 UTC, e.g. 2027-05-09T00:00:00Z, or null for no expiry.
     */
    public static function isoUtc(License $license): ?string
    {
        return $license->expires_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    private function secretKey(): string
    {
        $key = base64_decode((string) config('licensing.license_secret_key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('OCCA_LICENSE_SECRET_KEY is missing or is not a base64 Ed25519 secret key.');
        }

        return $key;
    }

    private function publicKey(): string
    {
        $key = base64_decode((string) config('licensing.license_public_key'), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('OCCA_LICENSE_PUBLIC_KEY is missing or is not a base64 Ed25519 public key.');
        }

        return $key;
    }
}
