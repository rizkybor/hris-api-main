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
        // Manual recurring billing (website maintenance, domain renewal,
        // SaaS subscriptions like Ticket Management/Jstock) -- staff click
        // "Generate Invoice" themselves each period rather than a job
        // auto-creating invoices, so this is just the recurring "contract"
        // record: what's owed, how often, and when it's next due.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 'website_maintenance', 'domain_renewal', 'saas_subscription'
            // -- plain string, configurable via Settings -> Dropdown Options
            // (ConfigurableOption category "subscription_service_type")
            // rather than a DB enum, so a new service type is just a new
            // enum case, no migration needed.
            $table->string('service_type');
            // SaaS only (e.g. "Ticket Management (Yaap)", "Jstock") --
            // free text with autocomplete on the frontend rather than a
            // fixed list, so a new product doesn't need a migration either.
            $table->string('product_name')->nullable();
            // Maintenance only, when it's tied to a specific website
            // project -- domain renewals and SaaS subscriptions usually
            // aren't project-scoped.
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('billing_cycle'); // 'monthly' | 'yearly'
            $table->decimal('amount', 15, 2);
            $table->date('start_date');
            // Drives the "due for renewal" list -- advanced by one billing
            // cycle each time an invoice is generated for this subscription.
            $table->date('next_due_date');
            $table->string('status')->default('active'); // 'active' | 'postponed' | 'cancelled' (shown to users as "Not Active")
            $table->date('last_invoiced_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
