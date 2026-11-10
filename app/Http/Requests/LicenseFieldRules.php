<?php

namespace App\Http\Requests;

use App\Models\License;
use App\Support\IndonesianRegions;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation rules shared by the store / update requests and the
 * license:create command, so the HTTP API and the CLI accept exactly
 * the same licensee details.
 */
class LicenseFieldRules
{
    /**
     * Phone / WhatsApp: 6-20 characters of digits, spaces, "+" and "-".
     */
    public const PHONE_REGEX = '/^[0-9+\- ]{6,20}$/';

    /**
     * Rules for the licensee details of a category. Required fields of the
     * category get "required" ($partial adds "sometimes", for an update that
     * keeps the category and only sends some fields; $partialLocation does
     * the same for the location group); every other field is nullable (the
     * other category's fields are cleared on save anyway). The location
     * rules depend on the country: Indonesia needs the region code chain
     * and a 5-digit postal code.
     */
    public static function profile(string $category, bool $partial = false, ?string $country = License::DEFAULT_COUNTRY, ?bool $partialLocation = null): array
    {
        $partialLocation ??= $partial;
        $required = License::REQUIRED_FIELDS[$category];
        $requiredLocation = License::requiredLocationFields($category, $country);
        $indonesia = IndonesianRegions::isIndonesia($country);

        $rules = [
            'licensee_name' => ['string', 'max:255'],
            'licensee_email' => ['string', 'email', 'max:255'],
            'phone' => ['string', 'min:6', 'max:20', 'regex:'.self::PHONE_REGEX],
            'country' => ['string', 'max:255'],
            'province' => ['string', 'max:255'],
            'regency' => ['string', 'max:255'],
            'district' => ['string', 'max:255'],
            'village' => ['string', 'max:255'],
            'postal_code' => $indonesia
                ? ['string', 'regex:/^[0-9]{5}$/']
                : ['string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{1,19}$/'],
            'province_code' => ['string', 'max:13', 'regex:/^[0-9.]+$/'],
            'regency_code' => ['string', 'max:13', 'regex:/^[0-9.]+$/'],
            'district_code' => ['string', 'max:13', 'regex:/^[0-9.]+$/'],
            'village_code' => ['string', 'max:13', 'regex:/^[0-9.]+$/'],
            'occupation' => ['string', 'max:255'],
            'affiliation' => ['string', 'max:255'],
            'organization_type' => ['string', Rule::in(License::ORGANIZATION_TYPES)],
            'contact_name' => ['string', 'max:255'],
            'contact_position' => ['string', 'max:255'],
            'address' => ['string', 'max:1000'],
            'website' => ['string', 'url', 'max:255'],
            'tax_id' => ['string', 'max:50'],
            'member_count' => ['integer', 'min:1', 'max:10000000'],
        ];

        foreach ($rules as $field => $fieldRules) {
            $isLocation = in_array($field, License::LOCATION_FIELDS, true);
            $isRequired = $isLocation ? in_array($field, $requiredLocation, true) : in_array($field, $required, true);
            $isPartial = $isLocation ? $partialLocation : $partial;
            $prefix = $isRequired
                ? ($isPartial ? ['sometimes', 'required'] : ['required'])
                : ['nullable'];
            $rules[$field] = array_merge($prefix, $fieldRules);
        }

        return $rules;
    }

    /**
     * For Indonesia: every region code must exist and belong to its parent.
     */
    public static function addRegionErrors(Validator $validator, array $input): void
    {
        if (! IndonesianRegions::isIndonesia($input['country'] ?? null)) {
            return;
        }

        foreach (IndonesianRegions::chainErrors($input) as $field => $message) {
            if (! $validator->errors()->has($field)) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    /**
     * Plan / type pairing (the type follows the plan): the Lifetime plan is
     * always lifetime, every other plan is term. Returns the message for the
     * "type" field, or null.
     */
    public static function planTypeError(?string $plan, ?string $type): ?string
    {
        if (in_array($plan, License::TERM_ONLY_PLANS, true) && $type === License::TYPE_LIFETIME) {
            return 'A '.License::PLAN_LABELS[$plan].' license must be a term license, not lifetime.';
        }
        if ($plan === License::PLAN_LIFETIME && $type === License::TYPE_TERM) {
            return 'A Lifetime license must be of type lifetime, not term.';
        }

        return null;
    }

    public static function addPlanTypeError(Validator $validator, ?string $plan, ?string $type): void
    {
        if ($message = self::planTypeError($plan, $type)) {
            $validator->errors()->add('type', $message);
        }
    }

    /**
     * A term license needs a duration preset or an expiry date.
     */
    public static function addTermExpiryError(Validator $validator, ?string $type, mixed $duration, mixed $expiresAt): void
    {
        if ($type === License::TYPE_TERM && blank($duration) && blank($expiresAt) && ! $validator->errors()->has('duration')) {
            $validator->errors()->add('duration', 'Choose a duration or an expiry date for a term license.');
        }
    }

    /**
     * iPad has no Trial: a plan must be one sold on the license's platform.
     */
    public static function addPlatformPlanError(Validator $validator, ?string $platform, ?string $plan): void
    {
        if (! in_array($platform, License::PLATFORMS, true) || ! in_array($plan, License::PLANS, true) || $validator->errors()->has('plan')) {
            return;
        }

        if (! License::planAllowedOn($platform, $plan)) {
            $validator->errors()->add('plan', 'The '.License::PLAN_LABELS[$plan].' plan is not available for '.License::PLATFORM_LABELS[$platform].' licenses.');
        }
    }

    public static function attributes(): array
    {
        return [
            'platform' => 'Platform',
            'licensee_name' => 'Name',
            'licensee_email' => 'Email',
            'phone' => 'Phone',
            'province' => 'Province',
            'regency' => 'City / Regency',
            'district' => 'District',
            'village' => 'Village',
            'postal_code' => 'Postal Code',
            'province_code' => 'Province',
            'regency_code' => 'City / Regency',
            'district_code' => 'District',
            'village_code' => 'Village',
            'country' => 'Country',
            'occupation' => 'Occupation',
            'affiliation' => 'Affiliation',
            'organization_type' => 'Organization Type',
            'contact_name' => 'Contact Name',
            'contact_position' => 'Contact Position',
            'address' => 'Street Address',
            'website' => 'Website',
            'tax_id' => 'Tax ID',
            'member_count' => 'Member Count',
            'plan' => 'Plan',
            'type' => 'Type',
            'duration' => 'Duration',
            'expires_at' => 'Expires At',
            'max_devices' => 'Max Devices',
            'notes' => 'Notes',
            'is_active' => 'Active',
        ];
    }

    public static function messages(): array
    {
        return [
            'postal_code.regex' => 'The :attribute must be 5 digits for Indonesia, or 2-20 letters, digits, spaces or "-" elsewhere.',
            'platform.in' => 'The :attribute must be Mac or iPad, and cannot be changed after the license is created.',
            'phone.regex' => 'The :attribute may only contain digits, spaces, "+" and "-" (6-20 characters).',
        ];
    }
}
