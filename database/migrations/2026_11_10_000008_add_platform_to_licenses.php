<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate Mac and iPad licenses:
     * - licenses.platform: mac | ipad, fixed at creation;
     * - the key's two-digit code becomes platform digit + plan digit
     *   (Mac 31-34, iPad 52-54; iPad has no Trial), e.g. OC32-7KQM-2XRT-9HZP-0001;
     * - every platform has its own number sequence per product
     *   (license_products.mac_license_sequence / ipad_license_sequence),
     *   so licenses.sequence is unique per product + platform.
     *
     * The apps are not released yet, so existing licenses are reset to the
     * new format: all become Mac licenses, are renumbered in creation order
     * and get a fresh key. Devices keep their activation (same device_id
     * re-activates without a new seat), but have to enter the new key.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const PLAN_DIGITS = ['trial' => '1', 'professional' => '2', 'lifetime' => '3', 'organization' => '4'];

    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('platform', 10)->default('mac')->after('plan');
            $table->dropUnique(['product_id', 'sequence']);
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->renameColumn('license_sequence', 'mac_license_sequence');
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->unsignedInteger('ipad_license_sequence')->default(0)->after('mac_license_sequence');
        });

        $used = [];
        foreach (DB::table('license_products')->get(['id', 'key_prefix']) as $product) {
            $number = 0;
            $licenses = DB::table('licenses')
                ->where('product_id', $product->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'plan']);

            foreach ($licenses as $license) {
                $number++;
                $head = strtoupper($product->key_prefix).'3'.(self::PLAN_DIGITS[$license->plan] ?? '2');
                $tail = str_pad((string) $number, 4, '0', STR_PAD_LEFT);

                do {
                    $key = $head.'-'.$this->group().'-'.$this->group().'-'.$this->group().'-'.$tail;
                } while (isset($used[$key]) || DB::table('licenses')->where('key', $key)->exists());
                $used[$key] = true;

                DB::table('licenses')->where('id', $license->id)->update([
                    'platform' => 'mac',
                    'sequence' => $number,
                    'key' => $key,
                    'updated_at' => now(),
                ]);
            }

            DB::table('license_products')->where('id', $product->id)->update([
                'mac_license_sequence' => $number,
                'ipad_license_sequence' => 0,
            ]);
        }

        Schema::table('licenses', function (Blueprint $table) {
            $table->unique(['product_id', 'platform', 'sequence']);
        });
    }

    /**
     * Structure only: the reset keys stay in the new format.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'platform', 'sequence']);
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn('ipad_license_sequence');
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->renameColumn('mac_license_sequence', 'license_sequence');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('platform');
            $table->unique(['product_id', 'sequence']);
        });
    }

    private function group(): string
    {
        $group = '';
        for ($i = 0; $i < 4; $i++) {
            $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $group;
    }
};
