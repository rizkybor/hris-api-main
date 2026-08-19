<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            // Scheduled backups have no causing user -- created_by must be
            // nullable to support them, and the FK's cascadeOnDelete has to
            // go too (a user being deleted shouldn't silently wipe out
            // backup history that was correctly attributed to them).
            $table->dropForeign(['created_by']);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->boolean('is_automatic')->default(false)->after('created_by');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        // Automatic backups have no creator to restore, so they'd violate
        // a re-tightened NOT NULL constraint -- drop them first.
        \App\Models\Backup::whereNull('created_by')->delete();

        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('is_automatic');
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
