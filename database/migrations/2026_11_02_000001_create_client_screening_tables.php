<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Screening: a needs questionnaire (filled by the team or by the
 * client from the landing page) that recommends which Rate Card services to
 * offer. Prices stay internal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The services on offer, mirroring the Rate Card (Notion).
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('category', 60);
            $table->text('description')->nullable();
            // rate_card: fixed "start from" price; hourly: hours x Project
            // Calculator sell rate; by_quote: no price.
            $table->string('pricing_mode', 20)->default('rate_card');
            $table->decimal('price_from', 15, 2)->nullable();
            $table->string('price_unit', 10)->default('once'); // once | month | year
            $table->decimal('default_hours', 8, 2)->nullable();
            $table->string('duration_text', 100)->nullable();
            $table->string('availability', 20)->default('available'); // available | preparation
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('rate_card_ref', 100)->nullable(); // Notion row id
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('screening_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('definition'); // sections > questions > options (+ rules), ID/EN text
            $table->boolean('is_default')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('screenings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('token', 64)->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('source', 20); // landing | invite | internal
            $table->string('status', 20)->default('draft'); // draft | invited | new | in_review | reviewed | won | lost
            $table->string('lang', 2)->default('id');
            $table->string('contact_name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('screening_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('template_snapshot'); // the questions as answered
            $table->json('answers')->nullable();
            $table->json('result')->nullable(); // system types, complexity, flags, budget fit
            $table->text('internal_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        // Recommended (or manually added) items, editable by the team.
        Schema::create('screening_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('unit_price', 15, 2)->nullable(); // null = by quote
            $table->string('price_unit', 10)->default('once');
            $table->string('duration_text', 100)->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_selected')->default(true);
            $table->string('source', 10)->default('auto'); // auto | manual
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_items');
        Schema::dropIfExists('screenings');
        Schema::dropIfExists('screening_templates');
        Schema::dropIfExists('service_items');
    }
};
