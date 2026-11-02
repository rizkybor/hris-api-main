<?php

namespace Database\Seeders;

use App\Models\ScreeningTemplate;
use App\Models\ServiceItem;
use App\Support\ScreeningDefaults;
use Illuminate\Database\Seeder;

/**
 * Service catalog (Rate Card values + extras) and the default screening
 * template. Safe to re-run: catalog rows are matched by code and existing
 * prices are left alone; the template is only created when there is none.
 */
class ClientScreeningSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ScreeningDefaults::catalog() as $row) {
            $item = ServiceItem::firstOrNew(['code' => $row['code']]);
            if (! $item->exists) {
                $item->fill($row + ['is_active' => true])->save();
            }
        }

        if (! ScreeningTemplate::exists()) {
            ScreeningTemplate::create([
                'name' => 'Default',
                'definition' => ScreeningDefaults::template(),
                'is_default' => true,
            ]);
        }
    }
}
