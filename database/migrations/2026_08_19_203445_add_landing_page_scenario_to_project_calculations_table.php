<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Laravel's Schema builder can't alter an existing enum's allowed
        // values cross-DB-safely, so this is raw SQL -- same approach as
        // every other enum widening in this codebase.
        DB::statement("ALTER TABLE project_calculations MODIFY scenario ENUM('feature', 'build', 'landing_page') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // A row saved with scenario='landing_page' would violate the
        // re-narrowed enum, so it has to go before the column is narrowed.
        DB::table('project_calculations')->where('scenario', 'landing_page')->delete();

        DB::statement("ALTER TABLE project_calculations MODIFY scenario ENUM('feature', 'build') NOT NULL");
    }
};
