<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OCCA for Windows: a third license platform ("windows", key platform
     * digit 7, e.g. OC72-XXXX-XXXX-XXXX-0001) with its own number sequence
     * per product, next to mac_license_sequence and ipad_license_sequence.
     * licenses.platform already takes any 10-character value and the
     * (product_id, platform, sequence) unique index covers it.
     */
    public function up(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->unsignedInteger('windows_license_sequence')->default(0)->after('ipad_license_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn('windows_license_sequence');
        });
    }
};
