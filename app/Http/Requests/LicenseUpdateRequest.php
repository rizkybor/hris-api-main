<?php

namespace App\Http\Requests;

use App\DTOs\LicenseDto;
use App\Models\License;
use App\Models\LicenseProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LicenseUpdateRequest extends FormRequest
{
    private ?License $storedLicense = null;

    private bool $storedLoaded = false;

    private function stored(): ?License
    {
        if (! $this->storedLoaded) {
            // Scoped to the product in the URL; a license of another product
            // is not found here and the controller answers 404.
            $product = LicenseProduct::findByCode($this->route('product'));
            $this->storedLicense = $product ? License::forProduct($product)->find($this->route('license')) : null;
            $this->storedLoaded = true;
        }

        return $this->storedLicense;
    }

    /**
     * Category of the plan after this update (the stored plan when "plan"
     * is not sent).
     */
    private function targetCategory(): string
    {
        return License::categoryForPlan($this->input('plan', $this->stored()?->plan));
    }

    /**
     * Country after this update (the stored one when not sent).
     */
    private function targetCountry(): ?string
    {
        return $this->has('country') ? $this->input('country') : ($this->stored()?->country ?? License::DEFAULT_COUNTRY);
    }

    /**
     * Any location field sent: the location is then validated as a whole.
     */
    private function sendsLocation(): bool
    {
        foreach (License::LOCATION_FIELDS as $field) {
            if ($this->has($field)) {
                return true;
            }
        }

        return false;
    }

    private function changesCategory(): bool
    {
        $stored = $this->stored();

        return $stored !== null && $this->filled('plan') && $this->targetCategory() !== $stored->category();
    }

    /**
     * When the category changes, the new category's required fields are
     * checked against the merged record: values not sent fall back to the
     * stored ones (shared fields such as email / phone / the location carry
     * over), and country falls back to Indonesia.
     */
    protected function prepareForValidation(): void
    {
        // The type follows the plan whenever a plan is sent (a client type
        // is ignored). Without a plan the stored type is left alone, so
        // partial updates of older rows keep working.
        if (in_array($this->input('plan'), License::PLANS, true)) {
            $this->merge(['type' => License::typeForPlan($this->input('plan'))]);
        } elseif ($this->stored()) {
            $this->merge(['type' => $this->stored()->type]);
        }

        // Part of the location sent: the rest comes from the stored record,
        // so the location is always validated as a whole.
        if (! $this->changesCategory() && $this->sendsLocation() && $this->stored()) {
            $merge = [];
            foreach (License::LOCATION_FIELDS as $field) {
                if (! $this->has($field) && filled($this->stored()->{$field})) {
                    $merge[$field] = $this->stored()->{$field};
                }
            }
            $this->merge($merge);
        }

        if (! $this->changesCategory()) {
            return;
        }

        $stored = $this->stored();
        $merge = [];
        foreach (array_merge(License::REQUIRED_FIELDS[$this->targetCategory()], License::LOCATION_FIELDS) as $field) {
            if (! $this->has($field) && filled($stored->{$field})) {
                $merge[$field] = $stored->{$field};
            }
        }
        if (blank($this->input('country', $merge['country'] ?? null))) {
            $merge['country'] = License::DEFAULT_COUNTRY;
        }

        $this->merge($merge);
    }

    /**
     * The key itself is immutable, so it is not accepted here. A past
     * expires_at is allowed: toggling is_active resends the full record,
     * including the old date of an already expired term license.
     *
     * Licensee details: a field that is sent must satisfy the category's
     * rules ("sometimes"), so partial updates such as {"is_active": false}
     * keep working. When the plan moves to the other category, all of the
     * new category's required fields must be present (see above).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(LicenseFieldRules::profile(
            $this->targetCategory(),
            partial: ! $this->changesCategory(),
            country: $this->targetCountry(),
            partialLocation: ! $this->changesCategory() && ! $this->sendsLocation(),
        ), [
            // The platform is fixed at creation (it is part of the key);
            // resending the same value is fine.
            'platform' => ['sometimes', Rule::in(array_filter([$this->stored()?->platform]))],
            'plan' => ['sometimes', 'required', Rule::in(License::PLANS)],
            // Set from the plan in prepareForValidation().
            'type' => ['sometimes', Rule::in(License::TYPES)],
            'duration' => ['nullable', Rule::in(LicenseDto::DURATIONS)],
            'expires_at' => ['required_if:duration,'.LicenseDto::DURATION_CUSTOM, 'nullable', 'date_format:Y-m-d'],
            'max_devices' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Moving to a term plan from a license without an expiry (lifetime)
     * needs a duration or an expiry date.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->sendsLocation() || $this->changesCategory()) {
                    LicenseFieldRules::addRegionErrors($validator, array_merge(['country' => $this->targetCountry()], $this->all()));
                }
            },
            function (Validator $validator) {
                $license = $this->stored();
                if ($this->input('type') !== License::TYPE_TERM || ! $license || $license->expires_at !== null) {
                    return;
                }

                LicenseFieldRules::addTermExpiryError($validator, License::TYPE_TERM, $this->input('duration'), $this->input('expires_at'));
            },
            function (Validator $validator) {
                if ($this->filled('plan') && $this->stored()) {
                    LicenseFieldRules::addPlatformPlanError($validator, $this->stored()->platform, $this->input('plan'));
                }
            },
        ];
    }

    public function attributes()
    {
        return LicenseFieldRules::attributes();
    }

    public function messages()
    {
        return LicenseFieldRules::messages();
    }
}
