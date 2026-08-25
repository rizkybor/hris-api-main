<?php

namespace App\Console\Commands;

use App\Models\CompanyAbout;
use Illuminate\Console\Command;

/**
 * One-off repair for a historical bug: CompanyAboutDto::toArray() used to
 * json_encode() 'mission'/'branches' before handing them to the model, even
 * though the model already casts both as 'array' (which encodes/decodes on
 * its own) -- every save double-encoded the value, so reading it back left
 * a STRING that still looks like a JSON array instead of a real array. Safe
 * to run repeatedly: already-clean rows are left untouched.
 */
class FixCompanyAboutDoubleEncodedFields extends Command
{
    protected $signature = 'company-about:fix-double-encoded-fields';

    protected $description = 'Repair CompanyAbout mission/branches values left double-JSON-encoded by a historical bug';

    public function handle(): int
    {
        $fixed = 0;

        CompanyAbout::withTrashed()->get()->each(function (CompanyAbout $company) use (&$fixed) {
            $changed = false;

            foreach (['mission', 'branches'] as $field) {
                $value = $company->{$field};
                $original = $value;

                // A correctly-cast array attribute is already an array (or
                // null) at this point. If it's still a string, the model's
                // one decode wasn't enough -- keep decoding while it's a
                // JSON-parseable string, since a row edited more than once
                // while the bug was live can be encoded several layers deep.
                $guard = 0;
                while (is_string($value) && $guard < 5) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        break;
                    }
                    $value = $decoded;
                    $guard++;
                }

                if ($value !== $original) {
                    $company->{$field} = $value;
                    $changed = true;
                }
            }

            if ($changed) {
                $company->save();
                $fixed++;
                $this->line("Fixed company_about id={$company->id}");
            }
        });

        $this->info("Done. Fixed {$fixed} row(s).");

        return self::SUCCESS;
    }
}
