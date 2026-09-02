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
        Schema::create('analytics_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website_url')->nullable();
            // 'posthog', 'google_analytics_4', 'google_search_console' --
            // plain string rather than a DB enum so a new provider is just
            // a new AnalyticsSourceType case, no migration needed.
            $table->string('type');
            // Free-text grouping label (e.g. "Landing Page Jendela Cakra
            // Digital Analytics") -- sources sharing the same category
            // string are displayed together on the Analytics page. Not a
            // separate categories table: categories aren't independently
            // managed (renamed/deleted) here, just typed per source, so a
            // normalized table would only add ceremony without a use case
            // yet to justify it.
            $table->string('category');
            $table->text('embed_url');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_sources');
    }
};
