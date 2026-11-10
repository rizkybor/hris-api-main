<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'product_id' => $this->product_id,
            'sequence' => $this->sequence,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
                'key_prefix' => $this->product->key_prefix,
                'icon_url' => $this->product->iconUrl(),
                'is_active' => $this->product->is_active,
            ]),
            'plan' => $this->plan,
            'plan_label' => $this->planLabel(),
            'platform' => $this->platform,
            'platform_label' => $this->platformLabel(),
            // Mac + iPad generated together: the other key(s) of the bundle.
            'bundle_id' => $this->bundle_id,
            'bundle' => $this->whenLoaded('bundleMembers', fn () => $this->bundleSiblings()->map(fn ($sibling) => [
                'id' => $sibling->id,
                'key' => $sibling->key,
                'platform' => $sibling->platform,
                'platform_label' => $sibling->platformLabel(),
                'plan' => $sibling->plan,
                'is_active' => $sibling->is_active,
            ])->values()),
            'category' => $this->category(),
            'type' => $this->type,

            // Licensee details (shared).
            'licensee_name' => $this->licensee_name,
            'licensee_email' => $this->licensee_email,
            'phone' => $this->phone,
            // Location (both categories). For Indonesia the codes are set and
            // the names come from them.
            'country' => $this->country,
            'province' => $this->province,
            'regency' => $this->regency,
            'district' => $this->district,
            'village' => $this->village,
            'postal_code' => $this->postal_code,
            'province_code' => $this->province_code,
            'regency_code' => $this->regency_code,
            'district_code' => $this->district_code,
            'village_code' => $this->village_code,
            'address' => $this->address,
            'full_address' => $this->fullAddress(),
            // Individual only.
            'occupation' => $this->occupation,
            'affiliation' => $this->affiliation,
            // Organization only.
            'organization_type' => $this->organization_type,
            'organization_type_label' => $this->organizationTypeLabel(),
            'contact_name' => $this->contact_name,
            'contact_position' => $this->contact_position,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'member_count' => $this->member_count,

            // Active period.
            'status' => $this->status,
            'is_expired' => $this->isExpired(),
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'duration_days' => $this->durationDays(),
            'remaining_days' => $this->remainingDays(),
            'elapsed_percent' => $this->elapsedPercent(),

            'updates_major' => $this->updates_major,
            'updates_label' => $this->updatesLabel(),
            'max_devices' => $this->max_devices,
            'devices_used' => $this->activations_count ?? $this->activations()->count(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'terms_version' => $this->terms_version,
            'last_activated_at' => $this->last_activated_at,
            'last_activity_at' => $this->lastActivityAt(),
            'activations' => LicenseActivationResource::collection($this->whenLoaded('activations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
