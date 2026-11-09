<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complete location for every license: province, regency (city /
     * Kota-Kabupaten), district (Kecamatan), village (Kelurahan/Desa) and
     * postal code, plus the region codes for Indonesian addresses. "city"
     * is moved into "regency" and dropped.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('province')->nullable()->after('phone');
            $table->string('regency')->nullable()->after('province');
            $table->string('district')->nullable()->after('regency');
            $table->string('village')->nullable()->after('district');
            $table->string('postal_code', 20)->nullable()->after('village');
            $table->string('province_code', 2)->nullable()->after('postal_code');
            $table->string('regency_code', 5)->nullable()->after('province_code');
            $table->string('district_code', 8)->nullable()->after('regency_code');
            $table->string('village_code', 13)->nullable()->after('district_code');
        });

        DB::table('licenses')->whereNotNull('city')->update(['regency' => DB::raw('city')]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('city')->nullable()->after('phone');
        });

        DB::table('licenses')->whereNotNull('regency')->update(['city' => DB::raw('regency')]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn([
                'province', 'regency', 'district', 'village', 'postal_code',
                'province_code', 'regency_code', 'district_code', 'village_code',
            ]);
        });
    }
};
