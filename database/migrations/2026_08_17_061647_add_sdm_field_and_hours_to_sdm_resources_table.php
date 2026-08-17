<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->foreignId('sdm_field_id')->nullable()->after('id')->constrained('sdm_fields')->nullOnDelete();
            $table->decimal('productive_hours_per_month', 8, 2)->nullable()->after('sdm_field_id');
        });

        // metrik / capacity_target were required KPI-style fields for the old
        // "resource tracking line item" model; now that a row represents a
        // team member (used to derive Rate Setup's team size & productive
        // hours), they're optional context rather than mandatory input.
        // Raw SQL (rather than ->change(), which needs doctrine/dbal) so
        // existing rows keep their data instead of a destructive
        // drop-and-recreate.
        DB::statement('ALTER TABLE sdm_resources MODIFY metrik VARCHAR(255) NULL');
        DB::statement('ALTER TABLE sdm_resources MODIFY capacity_target VARCHAR(255) NULL');
    }

    public function down(): void
    {
        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sdm_field_id');
            $table->dropColumn('productive_hours_per_month');
        });

        DB::statement('ALTER TABLE sdm_resources MODIFY metrik VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE sdm_resources MODIFY capacity_target VARCHAR(255) NOT NULL');
    }
};
