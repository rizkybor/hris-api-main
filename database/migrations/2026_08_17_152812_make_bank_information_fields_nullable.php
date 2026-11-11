<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('bank_name', 255)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY bank_name VARCHAR(255) NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_number', 255)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_number VARCHAR(255) NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_holder_name', 255)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_holder_name VARCHAR(255) NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_type', 255)->nullable()->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_type VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('bank_name', 255)->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY bank_name VARCHAR(255) NOT NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_number', 255)->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_number VARCHAR(255) NOT NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_holder_name', 255)->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_holder_name VARCHAR(255) NOT NULL');
        }
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (test suite) has no MODIFY; same column change via the schema builder.
            Schema::table('bank_information', fn (Blueprint $table) => $table->string('account_type', 255)->change());
        } else {
            DB::statement('ALTER TABLE bank_information MODIFY account_type VARCHAR(255) NOT NULL');
        }
    }
};
