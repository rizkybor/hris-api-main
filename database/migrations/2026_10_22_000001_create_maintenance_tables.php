<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance Management: monthly hour quotas per client contract, the work
 * logged against them, and the "development balance" that unused hours can
 * be moved into and later spent on new features.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('name');
            $table->decimal('monthly_quota_hours', 7, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active'); // active | paused | ended
            // What happens to a month's unused hours: expire | rollover | bank
            $table->string('leftover_policy', 20)->default('bank');
            // Development balance limits: null = no cap / never expires.
            $table->decimal('bank_cap_hours', 7, 2)->nullable();
            $table->unsignedSmallInteger('bank_expiry_months')->nullable()->default(6);
            $table->decimal('overage_rate', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('share_last_viewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();
            $table->date('period_month'); // first day of the month
            $table->decimal('quota_hours', 7, 2);
            $table->decimal('carried_hours', 7, 2)->default(0);
            // Overrides the contract's leftover_policy for this month only.
            $table->string('leftover_action', 20)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('leftover_hours', 7, 2)->nullable();
            $table->string('leftover_applied', 20)->nullable();
            $table->decimal('leftover_banked_hours', 7, 2)->nullable();
            $table->timestamps();

            $table->unique(['maintenance_contract_id', 'period_month']);
        });

        Schema::create('maintenance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 7, 2); // signed
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_hours', 7, 2);
            // proposed | approved | in_progress | done | cancelled
            $table->string('status', 20)->default('proposed');
            $table->decimal('reserved_hours', 7, 2)->default(0);
            $table->date('reserved_expires_at')->nullable();
            $table->decimal('billable_hours', 7, 2)->default(0);
            $table->boolean('visible_to_client')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();
            // Exactly one of these: routine maintenance (period) or a new
            // feature paid from the development balance.
            $table->foreignId('maintenance_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_feature_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('hours', 7, 2);
            $table->string('category', 40)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('visible_to_client')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Development balance ledger. Credits keep a running "remaining"
        // that debits draw down oldest-expiry-first, so expiry applies to
        // exactly the hours that are still unused.
        Schema::create('maintenance_bank_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();
            // leftover | adjust_in | refund | feature | adjust_out | expire
            $table->string('type', 20);
            $table->decimal('hours', 7, 2); // signed
            $table->decimal('remaining_hours', 7, 2)->nullable(); // credits only
            $table->date('expires_at')->nullable(); // credits only
            $table->foreignId('maintenance_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_feature_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_bank_entries');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenance_features');
        Schema::dropIfExists('maintenance_adjustments');
        Schema::dropIfExists('maintenance_periods');
        Schema::dropIfExists('maintenance_contracts');
    }
};
