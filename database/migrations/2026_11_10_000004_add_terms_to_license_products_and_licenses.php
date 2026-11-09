<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product-specific license terms (Markdown) with a version and an
     * effective date; every license keeps the terms version it was issued
     * under. OCCA's terms ship with the repo
     * (resources/license-products/terms/occa.md) and are seeded when empty.
     */
    public function up(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->longText('terms')->nullable()->after('icon_path');
            $table->string('terms_version', 20)->nullable()->after('terms');
            $table->date('terms_effective_at')->nullable()->after('terms_version');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('terms_version', 20)->nullable()->after('notes');
        });

        $source = resource_path('license-products/terms/occa.md');
        if (is_file($source)) {
            DB::table('license_products')
                ->where('code', 'occa')
                ->whereNull('terms')
                ->update([
                    'terms' => file_get_contents($source),
                    'terms_version' => '1.0',
                    'terms_effective_at' => '2026-11-10',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('terms_version');
        });

        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn(['terms', 'terms_version', 'terms_effective_at']);
        });
    }
};
