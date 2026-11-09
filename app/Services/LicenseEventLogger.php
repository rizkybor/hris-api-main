<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use Illuminate\Support\Carbon;

/**
 * The single place that writes license_events. Logging must never
 * break the request that triggers it: every failure is reported and
 * swallowed.
 */
class LicenseEventLogger
{
    /**
     * Record an event. $context may hold device_id, device_name, platform,
     * app_version, ip, user_id and meta. ip and user_id default to the
     * current request / authenticated admin; pass them (even as null) to
     * override.
     */
    public function log(License|int $license, string $event, array $context = []): ?LicenseEvent
    {
        try {
            return LicenseEvent::create([
                'license_id' => $license instanceof License ? $license->getKey() : $license,
                'event' => $event,
                'device_id' => $context['device_id'] ?? null,
                'device_name' => $context['device_name'] ?? null,
                'platform' => $context['platform'] ?? null,
                'app_version' => $context['app_version'] ?? null,
                'ip' => array_key_exists('ip', $context) ? $context['ip'] : $this->currentIp(),
                'user_id' => array_key_exists('user_id', $context) ? $context['user_id'] : $this->currentUserId(),
                'meta' => empty($context['meta']) ? null : $context['meta'],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * App event for one device; device details come from the activation
     * (or the raw request data when there is no activation, e.g. a denial).
     */
    public function logDevice(License $license, string $event, LicenseActivation|array $device, array $meta = []): ?LicenseEvent
    {
        $details = $device instanceof LicenseActivation ? $device->only(['device_id', 'device_name', 'platform', 'app_version']) : $device;

        return $this->log($license, $event, [
            'device_id' => $details['device_id'] ?? null,
            'device_name' => $details['device_name'] ?? null,
            'platform' => $details['platform'] ?? null,
            'app_version' => $details['app_version'] ?? null,
            'user_id' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * "refreshed" is logged at most once per device per 24 hours, so a
     * device that refreshes constantly does not flood the timeline.
     */
    public function logRefresh(License $license, LicenseActivation $activation): ?LicenseEvent
    {
        try {
            $recent = LicenseEvent::query()
                ->where('license_id', $license->getKey())
                ->where('event', LicenseEvent::REFRESHED)
                ->where('device_id', $activation->device_id)
                ->where('created_at', '>', now()->subHours(LicenseEvent::REFRESH_LOG_INTERVAL_HOURS))
                ->exists();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return $recent ? null : $this->logDevice($license, LicenseEvent::REFRESHED, $activation);
    }

    /**
     * Admin edit: one event per notable change (is_active, expires_at,
     * plan) plus an "updated" event with the remaining changed fields as
     * {field: {old, new}}. $original holds the raw values before the save,
     * $changes the raw changed values (Model::getChanges()).
     */
    public function logAdminChanges(License $license, array $original, array $changes): void
    {
        unset($changes['created_at'], $changes['updated_at'], $changes['deleted_at'], $changes['last_activated_at']);
        if (! $changes) {
            return;
        }

        if (array_key_exists('is_active', $changes)) {
            $this->log($license, (bool) $changes['is_active'] ? LicenseEvent::ACTIVATED_BY_ADMIN : LicenseEvent::DEACTIVATED_BY_ADMIN);
        }

        if (array_key_exists('expires_at', $changes)) {
            $meta = [
                'old_expires_at' => $this->iso($original['expires_at'] ?? null),
                'new_expires_at' => $this->iso($changes['expires_at']),
            ];
            if (array_key_exists('starts_at', $changes)) {
                $meta['old_starts_at'] = $this->iso($original['starts_at'] ?? null);
                $meta['new_starts_at'] = $this->iso($changes['starts_at']);
            }
            $this->log($license, LicenseEvent::EXTENDED, ['meta' => $meta]);
        }

        if (array_key_exists('plan', $changes)) {
            $this->log($license, LicenseEvent::PLAN_CHANGED, ['meta' => [
                'old_plan' => $original['plan'] ?? null,
                'new_plan' => $changes['plan'],
            ]]);
        }

        $covered = ['is_active', 'expires_at', 'plan'];
        if (array_key_exists('expires_at', $changes)) {
            $covered[] = 'starts_at';
        }
        $rest = array_diff_key($changes, array_flip($covered));
        if ($rest) {
            $diff = [];
            foreach ($rest as $field => $new) {
                $diff[$field] = ['old' => $original[$field] ?? null, 'new' => $new];
            }
            $this->log($license, LicenseEvent::UPDATED, ['meta' => ['changes' => $diff]]);
        }
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->utc()->toIso8601ZuluString();
    }

    private function currentIp(): ?string
    {
        try {
            return app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function currentUserId(): ?int
    {
        try {
            $id = auth()->id();

            return $id === null ? null : (int) $id;
        } catch (\Throwable) {
            return null;
        }
    }
}
