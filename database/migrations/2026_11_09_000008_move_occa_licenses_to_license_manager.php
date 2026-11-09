<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The OCCA license module becomes the generic License Manager. Tables
     * are renamed in place (existing rows survive), the license foreign key
     * column becomes license_id, and every license belongs to a product;
     * existing rows are OCCA licenses.
     */
    public function up(): void
    {
        Schema::rename('occa_licenses', 'licenses');
        Schema::rename('occa_license_activations', 'license_activations');
        Schema::rename('occa_license_events', 'license_events');

        // Activations: drop the FK (it still carries the old name), rename the
        // column, then add the FK back. Works on MySQL 5.7 and SQLite.
        Schema::table('license_activations', function (Blueprint $table) {
            // MySQL keeps the constraint name from before the table rename;
            // SQLite only drops foreign keys by column.
            Schema::getConnection()->getDriverName() === 'sqlite'
                ? $table->dropForeign(['occa_license_id'])
                : $table->dropForeign('occa_license_activations_occa_license_id_foreign');
        });
        Schema::table('license_activations', function (Blueprint $table) {
            $table->renameColumn('occa_license_id', 'license_id');
        });
        Schema::table('license_activations', function (Blueprint $table) {
            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
        });

        Schema::table('license_events', function (Blueprint $table) {
            $table->renameColumn('occa_license_id', 'license_id');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id');
        });

        $occaId = DB::table('license_products')->where('code', 'occa')->value('id');
        DB::table('licenses')->whereNull('product_id')->update(['product_id' => $occaId]);

        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable(false)->change();
        });
        Schema::table('licenses', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('license_products')->restrictOnDelete();
            $table->index(['product_id', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id', 'plan']);
        });
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });

        Schema::table('license_events', function (Blueprint $table) {
            $table->renameColumn('license_id', 'occa_license_id');
        });

        Schema::table('license_activations', function (Blueprint $table) {
            $table->dropForeign(['license_id']);
        });
        Schema::table('license_activations', function (Blueprint $table) {
            $table->renameColumn('license_id', 'occa_license_id');
        });

        Schema::rename('license_events', 'occa_license_events');
        Schema::rename('license_activations', 'occa_license_activations');
        Schema::rename('licenses', 'occa_licenses');

        Schema::table('occa_license_activations', function (Blueprint $table) {
            $table->foreign('occa_license_id', 'occa_license_activations_occa_license_id_foreign')
                ->references('id')->on('occa_licenses')->cascadeOnDelete();
        });
    }
};
