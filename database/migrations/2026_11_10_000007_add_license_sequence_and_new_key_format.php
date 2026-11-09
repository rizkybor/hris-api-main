<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New key format {prefix}{planCode}-XXXX-XXXX-XXXX-{sequence}, e.g.
     * OC01-7KQM-2XRT-9HZP-0001:
     * - license_products.license_sequence: per-product counter, incremented
     *   under a row lock on every create (numbers are never reused);
     * - licenses.sequence: the license's number within its product.
     * Existing licenses get numbers in creation order (their keys do not
     * change) and each counter starts at that maximum. OCCA's key prefix
     * becomes "OC".
     */
    public function up(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->unsignedInteger('license_sequence')->default(0)->after('key_prefix');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable()->after('key');
            $table->unique(['product_id', 'sequence']);
        });

        foreach (DB::table('license_products')->pluck('id') as $productId) {
            $number = 0;
            foreach (DB::table('licenses')->where('product_id', $productId)->orderBy('created_at')->orderBy('id')->pluck('id') as $licenseId) {
                DB::table('licenses')->where('id', $licenseId)->update(['sequence' => ++$number]);
            }
            DB::table('license_products')->where('id', $productId)->update(['license_sequence' => $number]);
        }

        DB::table('license_products')->where('code', 'occa')->where('key_prefix', 'OCCA')->update(['key_prefix' => 'OC', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('license_products')->where('code', 'occa')->where('key_prefix', 'OC')->update(['key_prefix' => 'OCCA']);

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'sequence']);
            $table->dropColumn('sequence');
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn('license_sequence');
        });
    }
};
