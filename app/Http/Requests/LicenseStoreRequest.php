<?php

namespace App\Http\Requests;

use App\DTOs\LicenseDto;
use App\Models\License;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LicenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The type follows the plan (any type the client sends is ignored), and
     * country defaults to Indonesia when it is not sent.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if (in_array($this->input('plan'), License::PLANS, true)) {
            $merge['type'] = License::typeForPlan($this->input('plan'));
        }
        if (blank($this->input('country'))) {
            $merge['country'] = License::DEFAULT_COUNTRY;
        }

        $this->merge($merge);
    }

    /**
     * Required licensee details depend on the category of the plan
     * (individual: trial / professional / lifetime; organization).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = License::categoryForPlan($this->input('plan'));

        return array_merge(LicenseFieldRules::profile($category, country: $this->input('country')), [
            // mac, ipad, or both (a Mac and an iPad key generated together).
            'platform' => ['required', Rule::in(License::CREATE_PLATFORMS)],
            'plan' => ['required', Rule::in(License::PLANS)],
            // Set from the plan in prepareForValidation().
            'type' => ['nullable', Rule::in(License::TYPES)],
            'duration' => ['nullable', Rule::in(LicenseDto::DURATIONS)],
            'expires_at' => ['required_if:duration,'.LicenseDto::DURATION_CUSTOM, 'nullable', 'date_format:Y-m-d', 'after:today'],
            'max_devices' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Lifetime only: the major version it gets updates for (default: the current one).
            'updates_major' => ['nullable', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Every term plan needs a duration or an expiry date.
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => LicenseFieldRules::addTermExpiryError($validator, $this->input('type'), $this->input('duration'), $this->input('expires_at')),
            fn (Validator $validator) => LicenseFieldRules::addRegionErrors($validator, $this->all()),
            fn (Validator $validator) => LicenseFieldRules::addPlatformPlanError($validator, $this->input('platform'), $this->input('plan')),
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
