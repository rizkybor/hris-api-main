<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name')->nullable();
            $table->enum('scenario', ['feature', 'build']);

            // Rate snapshot at the time this calculation was saved, so a
            // later change to the company-wide rate setting never rewrites
            // the price of a quote that was already given to a client.
            $table->decimal('rate_cost_per_hour', 15, 2);
            $table->decimal('rate_sell_per_hour', 15, 2);
            $table->decimal('pm_overhead_percent', 5, 2)->nullable();
            $table->decimal('infra_setup_cost', 15, 2)->nullable();

            $table->json('items');

            $table->decimal('subtotal', 15, 2);
            $table->decimal('buffer_total', 15, 2)->default(0);
            $table->decimal('pm_overhead_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);

            $table->boolean('include_ppn')->default(false);
            $table->decimal('ppn_percent', 5, 2)->default(11);
            $table->decimal('total_with_ppn', 15, 2)->nullable();

            $table->unsignedInteger('estimated_duration_weeks')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('scenario');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_calculations');
    }
};
