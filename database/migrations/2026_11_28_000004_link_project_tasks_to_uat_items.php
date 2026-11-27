<?php

use App\Models\ProjectTask;
use App\Services\UatTaskSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A board task can come from a UAT finding/note: it lives and dies with
     * that item (To Do while open, Done once revised).
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('project_uat_item_id')->nullable()->unique()->after('project_id')
                ->constrained('project_uat_items')->cascadeOnDelete();
        });

        // Existing findings/notes get their task too.
        app(UatTaskSync::class)->syncAll();
    }

    public function down(): void
    {
        ProjectTask::withTrashed()->whereNotNull('project_uat_item_id')->forceDelete();
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_uat_item_id');
        });
    }
};
