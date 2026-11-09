<?php

namespace App\Console\Commands;

use App\DTOs\LicenseDto;
use App\Http\Requests\LicenseFieldRules;
use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use App\Models\LicenseProduct;
use App\Support\IndonesianRegions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LicenseCreateCommand extends Command
{
    protected $signature = 'license:create
        {--product=occa : Product code, e.g. occa}
        {--name= : Licensee name (organization name for --plan=organization)}
        {--email= : Licensee / contact email}
        {--phone= : Phone or WhatsApp (digits, spaces, + and -)}
        {--country=Indonesia : Country}
        {--village-code= : Indonesia: village (Kelurahan/Desa) code, e.g. 31.71.01.1001; province, city / regency and district follow from it}
        {--province= : Outside Indonesia: state / province}
        {--city= : Outside Indonesia: city}
        {--district= : Outside Indonesia: district (optional)}
        {--postal-code= : Postal code (5 digits in Indonesia)}
        {--occupation= : Individual: role, e.g. Mapper, Course setter, Coach (optional)}
        {--affiliation= : Individual: club or school (optional)}
        {--org-type= : Organization: team, community, club or federation}
        {--contact-name= : Organization: contact person}
        {--contact-position= : Organization: contact person position (optional)}
        {--address= : Street address (required for organizations)}
        {--website= : Organization: website URL (optional)}
        {--tax-id= : Organization: tax ID / NPWP (optional)}
        {--member-count= : Organization: number of members (optional)}
        {--plan=professional : Plan: trial, professional, lifetime or organization}
        {--lifetime : Optional with --plan=lifetime (implied); rejected for every other plan}
        {--months= : Term length in months (for a term license)}
        {--days= : Term length in days (for a term license, e.g. a trial)}
        {--max-devices=2 : Number of devices the key may be activated on}
        {--notes= : Internal notes (optional)}';

    protected $description = 'Create a License Manager key for a product and print it';

    /**
     * occa:license-create keeps working; its --product defaults to occa.
     */
    protected $aliases = ['occa:license-create'];

    /**
     * Option name for each validated field, so errors name the option.
     */
    private const OPTIONS = [
        'licensee_name' => 'name',
        'licensee_email' => 'email',
        'phone' => 'phone',
        'country' => 'country',
        'province' => 'province',
        'regency' => 'city',
        'district' => 'district',
        'postal_code' => 'postal-code',
        'province_code' => 'village-code',
        'regency_code' => 'village-code',
        'district_code' => 'village-code',
        'village_code' => 'village-code',
        'occupation' => 'occupation',
        'affiliation' => 'affiliation',
        'organization_type' => 'org-type',
        'contact_name' => 'contact-name',
        'contact_position' => 'contact-position',
        'address' => 'address',
        'website' => 'website',
        'tax_id' => 'tax-id',
        'member_count' => 'member-count',
        'plan' => 'plan',
        'months' => 'months',
        'days' => 'days',
        'max_devices' => 'max-devices',
    ];

    public function handle(LicenseRepositoryInterface $repository): int
    {
        $product = LicenseProduct::findByCode((string) $this->option('product'));
        if (! $product) {
            $this->error('Unknown --product "'.$this->option('product').'". Known: '.LicenseProduct::query()->pluck('code')->implode(', ').'.');

            return self::FAILURE;
        }

        $input = [];
        foreach (self::OPTIONS as $field => $option) {
            $value = $this->option($option);
            $input[$field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        // Indonesia: the whole region chain follows from --village-code.
        if (IndonesianRegions::isIndonesia($input['country'])) {
            $input = array_merge($input, IndonesianRegions::chainFromVillage((string) $input['village_code']));
            $input['province'] = $input['regency'] = $input['district'] = null;
        } else {
            foreach (License::REGION_CODE_FIELDS as $field) {
                $input[$field] = null;
            }
        }

        // Same licensee rules as the HTTP API (LicenseFieldRules).
        $category = License::categoryForPlan($input['plan']);
        $validator = Validator::make($input, array_merge(LicenseFieldRules::profile($category, country: $input['country']), [
            'plan' => ['required', Rule::in(License::PLANS)],
            'months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:100'],
        ]), LicenseFieldRules::messages(), array_map(fn ($option) => '--'.$option, self::OPTIONS));

        $validator->after(fn ($validator) => LicenseFieldRules::addRegionErrors($validator, $input));

        if ($validator->fails()) {
            foreach (array_unique($validator->errors()->all()) as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // The type follows the plan: --plan=lifetime is a lifetime license
        // (--lifetime optional), every other plan is term and needs exactly
        // one of --months / --days.
        $lifetime = $input['plan'] === License::PLAN_LIFETIME;
        $type = License::typeForPlan($input['plan']);

        if ($this->option('lifetime') && ! $lifetime) {
            $this->error('--lifetime is only for --plan=lifetime; a '.License::PLAN_LABELS[$input['plan']].' license is a term license. Use --months=N or --days=N.');

            return self::FAILURE;
        }

        if ($lifetime && ($input['months'] !== null || $input['days'] !== null)) {
            $this->error('A Lifetime license never expires; drop --months / --days.');

            return self::FAILURE;
        }

        if (! $lifetime && ($input['months'] === null) === ($input['days'] === null)) {
            $this->error('Pass exactly one of --months=N or --days=N.');

            return self::FAILURE;
        }

        $expiresAt = match (true) {
            $lifetime => null,
            $input['days'] !== null => now()->addDays((int) $input['days']),
            default => now()->addMonthsNoOverflow((int) $input['months']),
        };

        $license = $repository->create($product, array_merge(
            array_diff_key($input, array_flip(['months', 'days', 'max_devices'])),
            [
                'type' => $type,
                'duration' => $lifetime ? null : LicenseDto::DURATION_CUSTOM,
                'expires_at' => $expiresAt?->toDateTimeString(),
                'max_devices' => (int) $input['max_devices'],
                'notes' => $this->option('notes'),
                'is_active' => true,
                'source' => 'cli',
            ]
        ));

        $this->info($product->name.' license created.');
        $rows = [
            ['Product', $product->name],
            ['Key', $license->key],
            ['Licensee', $license->licensee_name],
            ['Email', $license->licensee_email ?? '-'],
            ['Phone', $license->phone ?? '-'],
            ['Address', $license->fullAddress() ?? '-'],
        ];
        if ($license->category() === License::CATEGORY_ORGANIZATION) {
            $rows[] = ['Organization type', $license->organizationTypeLabel()];
            $rows[] = ['Contact', $license->contact_name];
        }
        $this->table(['Field', 'Value'], array_merge($rows, [
            ['Plan', $license->planLabel()],
            ['Type', $license->type],
            ['Expires', $license->expires_at?->toDateTimeString() ?? 'never'],
            ['Max devices', $license->max_devices],
        ]));
        $this->line($license->key);

        return self::SUCCESS;
    }
}
