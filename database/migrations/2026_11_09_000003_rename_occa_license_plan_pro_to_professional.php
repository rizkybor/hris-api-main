<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first draft of the create migration stored plan "pro". Rename it to
     * "professional" on databases that already ran that draft. A plain UPDATE,
     * so it runs on MySQL and SQLite alike. The column default itself is set
     * in the create migration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('occa_licenses')) {
            return;
        }

        DB::table('occa_licenses')->where('plan', 'pro')->update(['plan' => 'professional']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('occa_licenses')) {
            return;
        }

        DB::table('occa_licenses')->where('plan', 'professional')->update(['plan' => 'pro']);
    }
};
