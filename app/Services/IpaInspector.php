<?php

namespace App\Services;

use App\Support\Plist;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use ZipArchive;

/**
 * Reads an iPad installer (.ipa, a zip with Payload/<App>.app/) without
 * unpacking it: the app's Info.plist (bundle id, version, build, minimum
 * iPadOS) and, when present, embedded.mobileprovision (profile type,
 * team, expiry and the iPads it was provisioned for).
 */
class IpaInspector
{
    public const PROFILE_AD_HOC = 'ad_hoc';

    public const PROFILE_DEVELOPMENT = 'development';

    public const PROFILE_ENTERPRISE = 'enterprise';

    public const PROFILE_APP_STORE = 'app_store';

    public const PROFILE_LABELS = [
        self::PROFILE_AD_HOC => 'Ad Hoc',
        self::PROFILE_DEVELOPMENT => 'Development',
        self::PROFILE_ENTERPRISE => 'Enterprise (in-house)',
        self::PROFILE_APP_STORE => 'App Store / TestFlight',
    ];

    /**
     * Largest Info.plist / profile read from the archive (bytes).
     */
    private const MAX_ENTRY_BYTES = 4 * 1024 * 1024;

    /**
     * @return array{bundle_id: ?string, short_version: ?string, build: ?string, name: ?string, minimum_os: ?string, profile: ?array}
     *
     * @throws InvalidArgumentException when the file is not an iOS app archive
     */
    public function inspect(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('The file is not a valid .ipa (zip) archive.');
        }

        try {
            $appDir = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#^Payload/([^/]+\.app)/Info\.plist$#', $name, $m)) {
                    $appDir = 'Payload/'.$m[1];
                    break;
                }
            }
            if ($appDir === null) {
                throw new InvalidArgumentException('The .ipa has no Payload/<App>.app/Info.plist.');
            }

            $info = Plist::parse($this->entry($zip, "$appDir/Info.plist") ?? '');
            if (! is_array($info)) {
                throw new InvalidArgumentException('The app\'s Info.plist is unreadable.');
            }

            $profileData = $this->entry($zip, "$appDir/embedded.mobileprovision");
        } finally {
            $zip->close();
        }

        return [
            'bundle_id' => $this->str($info['CFBundleIdentifier'] ?? null),
            'short_version' => $this->str($info['CFBundleShortVersionString'] ?? null),
            'build' => $this->str($info['CFBundleVersion'] ?? null),
            'name' => $this->str($info['CFBundleDisplayName'] ?? $info['CFBundleName'] ?? null),
            'minimum_os' => $this->str($info['MinimumOSVersion'] ?? null),
            'profile' => $profileData !== null ? $this->profile($profileData) : null,
        ];
    }

    /**
     * The provisioning profile: its plist sits in a CMS (PKCS#7) envelope;
     * the XML between "<?xml" and "</plist>" is read, the signature is not
     * verified (iPadOS does that on install).
     */
    public function profile(string $data): ?array
    {
        try {
            $plist = Plist::parseSignedXml($data);
        } catch (InvalidArgumentException) {
            return null;
        }
        if (! is_array($plist)) {
            return null;
        }

        $devices = array_values(array_filter(array_map(fn ($udid) => is_string($udid) ? $udid : null, $plist['ProvisionedDevices'] ?? [])));
        $entitlements = is_array($plist['Entitlements'] ?? null) ? $plist['Entitlements'] : [];
        $type = match (true) {
            ($plist['ProvisionsAllDevices'] ?? false) === true => self::PROFILE_ENTERPRISE,
            $devices !== [] && ($entitlements['get-task-allow'] ?? false) === true => self::PROFILE_DEVELOPMENT,
            $devices !== [] => self::PROFILE_AD_HOC,
            default => self::PROFILE_APP_STORE,
        };
        $expires = $this->str($plist['ExpirationDate'] ?? null);

        return [
            'name' => $this->str($plist['Name'] ?? null),
            'type' => $type,
            'type_label' => self::PROFILE_LABELS[$type],
            'team_id' => $this->str(($plist['TeamIdentifier'] ?? [])[0] ?? null),
            'team_name' => $this->str($plist['TeamName'] ?? null),
            'app_id' => $this->str($entitlements['application-identifier'] ?? $entitlements['com.apple.application-identifier'] ?? null),
            'created_at' => $this->str($plist['CreationDate'] ?? null),
            'expires_at' => $expires,
            'expired' => $expires !== null && Carbon::parse($expires)->isPast(),
            'device_count' => count($devices),
            'devices' => $devices,
        ];
    }

    private function entry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_ENTRY_BYTES) {
            throw new InvalidArgumentException("$name is too large.");
        }
        $content = $zip->getFromName($name);

        return $content === false ? null : $content;
    }

    private function str(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
