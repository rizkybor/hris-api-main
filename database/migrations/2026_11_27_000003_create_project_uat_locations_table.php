<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One UAT (e.g. UAT 1) can run as several sessions at the same time in
 * different places. Each finding/note may say which place it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_uat_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_uat_session_id')->constrained('project_uat_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('location', 150);
            $table->date('held_on')->nullable();
            $table->string('start_time', 5)->nullable(); // HH:MM
            $table->string('participants', 500)->nullable();
            $table->timestamps();

            $table->index(['project_uat_session_id', 'position']);
        });

        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->foreignId('project_uat_location_id')->nullable()->after('project_uat_session_id')
                ->constrained('project_uat_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_uat_location_id');
        });
        Schema::dropIfExists('project_uat_locations');
    }
};
