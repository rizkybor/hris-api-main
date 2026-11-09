<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One event in the cross-product "recent activity" feed.
 */
class RecentLicenseEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $license = $this->license;
        $product = $license?->product;

        return [
            'id' => $this->id,
            'event' => $this->event,
            'created_at' => $this->created_at,
            'device_name' => $this->device_name,
            'platform' => $this->platform,
            'ip' => $this->ip,
            'admin' => $this->user_id && $this->user ? ['name' => $this->user->name] : null,
            'license' => $license ? [
                'id' => $license->id,
                'key' => $license->key,
                'licensee_name' => $license->licensee_name,
            ] : null,
            'product' => $product ? [
                'code' => $product->code,
                'name' => $product->name,
                'icon_url' => $product->iconUrl(),
            ] : null,
        ];
    }
}
