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
        Schema::table('projects', function (Blueprint $table) {
            // Optional quick-access links, each a name+url pair so the
            // link can render with a readable label instead of a raw URL.
            $table->string('access_project_name')->nullable()->after('inspect_note');
            $table->string('access_project_url')->nullable()->after('access_project_name');
            $table->string('access_github_name')->nullable()->after('access_project_url');
            $table->string('access_github_url')->nullable()->after('access_github_name');
            $table->string('access_figma_name')->nullable()->after('access_github_url');
            $table->string('access_figma_url')->nullable()->after('access_figma_name');
            // Array of {name, url} pairs for any further link the fixed
            // Project/Github/Figma fields don't cover -- e.g. Staging,
            // Postman Collection, API Docs.
            $table->json('additional_access')->nullable()->after('access_figma_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'access_project_name',
                'access_project_url',
                'access_github_name',
                'access_github_url',
                'access_figma_name',
                'access_figma_url',
                'additional_access',
            ]);
        });
    }
};
