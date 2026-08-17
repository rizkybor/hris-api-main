<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bank Information is optional (e.g. for interns who have no payroll
     * account yet), so these columns must allow NULL. doctrine/dbal isn't
     * installed, so this uses raw ALTER TABLE instead of Blueprint::change().
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE bank_information MODIFY bank_name VARCHAR(255) NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_number VARCHAR(255) NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_holder_name VARCHAR(255) NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_type VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE bank_information MODIFY bank_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_number VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_holder_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE bank_information MODIFY account_type VARCHAR(255) NOT NULL');
    }
};
