<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the old free-form category values (laptop, phone, vehicle,
 * furniture, other) with the new fixed taxonomy codes used for asset_code
 * generation (IT-HW, IT-SW, OF-EQ, OF-FN, M-VEH, M-MISC). No schema change
 * -- `category` was always a plain string column.
 */
return new class extends Migration
{
    private const MAP = [
        'laptop' => 'IT-HW',
        'phone' => 'IT-HW',
        'vehicle' => 'M-VEH',
        'furniture' => 'OF-FN',
        'other' => 'M-MISC',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('company_assets')->where('category', $old)->update(['category' => $new]);
        }
    }

    public function down(): void
    {
        // Lossy: IT-HW could have originally been laptop or phone, so the
        // old distinction can't be recovered. Not reversed.
    }
};
