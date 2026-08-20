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
        Schema::table('project_tasks', function (Blueprint $table) {
            // Free-text per-task classification (projects use very
            // different task vocabularies) -- no separate management
            // table, just a name + a fixed palette key stored per task.
            $table->string('type')->nullable()->after('description');
            $table->string('color')->nullable()->after('type');

            // Trello-style fractional ordering within a (project_id,
            // status) group, so dragging a card only ever requires
            // updating the one row being moved -- new tasks get
            // max(position in column) + 1000, moves get the midpoint of
            // their new neighbors (or neighbor +/- 1000 at column ends).
            $table->double('position')->nullable()->after('status');

            $table->index(['project_id', 'status', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status', 'position']);
            $table->dropColumn(['type', 'color', 'position']);
        });
    }
};
