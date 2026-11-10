<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Release history of each product (version list): every version with
     * its new features, improvements and bug fixes. Versions follow
     * semantic versioning (major.minor.patch); a Lifetime license covers
     * one major version and its updates (licenses.updates_major).
     */
    public function up(): void
    {
        Schema::create('license_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('license_products')->cascadeOnDelete();
            $table->string('version', 20);
            $table->unsignedSmallInteger('major');
            $table->unsignedSmallInteger('minor');
            $table->unsignedSmallInteger('patch');
            // App build number (CFBundleVersion), optional.
            $table->unsignedInteger('build')->nullable();
            // both | mac | ipad
            $table->string('platforms', 10)->default('both');
            $table->string('title')->nullable();
            // draft | published; only published releases reach the apps.
            $table->string('status', 20)->default('draft');
            $table->date('released_at')->nullable();
            // Older versions must update (e.g. a bug that can damage projects).
            $table->boolean('is_critical')->default(false);
            $table->string('download_url', 500)->nullable();
            // [{type: feature|improvement|fix, area: string|null, text: string}, ...]
            $table->json('changes')->nullable();
            // Internal notes, never sent to the apps.
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'major', 'minor', 'patch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_releases');
    }
};
