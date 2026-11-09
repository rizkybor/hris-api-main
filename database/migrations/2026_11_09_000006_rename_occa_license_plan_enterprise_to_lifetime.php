<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "enterprise" plan was renamed to "lifetime" (label "Lifetime",
     * category individual, always type lifetime). A plain UPDATE, so it runs
     * on MySQL and SQLite alike.
     */
    public function up(): void
    {
        if (! Schema::hasTable('occa_licenses')) {
            return;
        }

        DB::table('occa_licenses')->where('plan', 'enterprise')->update(['plan' => 'lifetime']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('occa_licenses')) {
            return;
        }

        DB::table('occa_licenses')->where('plan', 'lifetime')->update(['plan' => 'enterprise']);
    }
};
