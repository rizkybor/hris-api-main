<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A UAT round holds several items, each a finding (temuan) or a note
 * (catatan), optionally tied to a feature and a menu. Replaces the old
 * one-finding-per-line text; existing lines become findings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_uat_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_uat_session_id')->constrained('project_uat_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            // finding | note
            $table->string('type', 10)->default('finding');
            $table->string('feature', 150)->nullable();
            $table->string('menu', 150)->nullable();
            $table->text('description');
            $table->timestamps();

            $table->index(['project_uat_session_id', 'position']);
        });

        $now = now();
        DB::table('project_uat_sessions')->whereNotNull('findings')->orderBy('id')->get(['id', 'findings'])
            ->each(function ($session) use ($now) {
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $session->findings))));
                foreach ($lines as $i => $line) {
                    DB::table('project_uat_items')->insert([
                        'project_uat_session_id' => $session->id,
                        'position' => $i,
                        'type' => 'finding',
                        'description' => $line,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        Schema::table('project_uat_sessions', function (Blueprint $table) {
            $table->dropColumn('findings');
        });
    }

    public function down(): void
    {
        Schema::table('project_uat_sessions', function (Blueprint $table) {
            $table->text('findings')->nullable()->after('notes');
        });
        DB::table('project_uat_items')->where('type', 'finding')->orderBy('position')->get()
            ->groupBy('project_uat_session_id')
            ->each(fn ($items, $sessionId) => DB::table('project_uat_sessions')->where('id', $sessionId)
                ->update(['findings' => $items->pluck('description')->implode("\n")]));
        Schema::dropIfExists('project_uat_items');
    }
};
