<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `type` was NOT NULL with no default even though VendorsStoreRequest
     * validates it as 'nullable' and the create/edit forms never mark it
     * required -- any submission that left it blank crashed with a DB
     * constraint violation (surfaced first as `VendorsDto::fromArray()`
     * reading an undefined array key, since the field is validated away
     * entirely when omitted). `email` had the exact same mismatch (NOT
     * NULL + unique, but validated as 'nullable' and optional in the UI).
     * Widening both to match what the validation/UI already treat as
     * optional -- a unique index still only rejects duplicate non-NULL
     * values in MySQL, so multiple vendors can each leave email blank.
     * `doctrine/dbal` isn't installed, so this uses a raw ALTER rather
     * than Schema::table(...)->change().
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('vendors', fn (Blueprint $table) => $table->string('type', 100)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE `vendors` MODIFY `type` VARCHAR(100) NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('vendors', fn (Blueprint $table) => $table->string('email', 255)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE `vendors` MODIFY `email` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('vendors', fn (Blueprint $table) => $table->string('type', 100)->default('')->change());
        } else {
            DB::statement("ALTER TABLE `vendors` MODIFY `type` VARCHAR(100) NOT NULL DEFAULT ''");
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('vendors', fn (Blueprint $table) => $table->string('email', 255)->default('')->change());
        } else {
            DB::statement("ALTER TABLE `vendors` MODIFY `email` VARCHAR(255) NOT NULL DEFAULT ''");
        }
    }
};
