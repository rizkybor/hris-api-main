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
        // Manual receipt numbering mode lets the user type the full
        // receipt number directly instead of a short client_code that
        // gets combined with the date/sequence -- client_code has no
        // value to store in that case. Raw SQL (not ->nullable()->change())
        // since doctrine/dbal isn't installed in this project.
        DB::statement('ALTER TABLE payment_receipts MODIFY client_code VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payment_receipts MODIFY client_code VARCHAR(255) NOT NULL');
    }
};
