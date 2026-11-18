<?php

namespace App\Http\Resources;

use App\Models\LicenseInstallLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LicenseInstallLink
 */
class LicenseInstallLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status();
        $code = $this->release?->product?->code;
        // The URL is only shown while the link can still be used.
        $token = $status === LicenseInstallLink::STATUS_ACTIVE && $code ? $this->token() : null;

        return [
            'id' => $this->id,
            'release_id' => $this->release_id,
            'version' => $this->release?->version,
            'status' => $status,
            'status_label' => LicenseInstallLink::STATUS_LABELS[$status] ?? $status,
            'url' => $token ? LicenseInstallLink::url($code, $token) : null,
            'expires_at' => $this->expires_at,
            'license' => $this->license ? [
                'id' => $this->license->id,
                'key' => $this->license->key,
                'licensee_name' => $this->license->licensee_name,
                'platform' => $this->license->platform,
            ] : null,
            'device_name' => $this->device_name,
            'note' => $this->note,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'created_at' => $this->created_at,
            'page_opened_at' => $this->page_opened_at,
            'manifest_fetched_at' => $this->manifest_fetched_at,
            'download_attempts' => $this->download_attempts,
            'used_at' => $this->used_at,
            'used_ip' => $this->used_ip,
            'used_user_agent' => $this->used_user_agent,
            'revoked_at' => $this->revoked_at,
            'revoked_by' => $this->revoker ? ['id' => $this->revoker->id, 'name' => $this->revoker->name] : null,
        ];
    }
}
