<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A subscription can now bundle several services (e.g. website
        // maintenance + domain renewal + a SaaS product) under one
        // recurring "contract" so Generate Invoice produces a single
        // invoice with one line item per service, instead of one
        // subscription per service.
        Schema::create('subscription_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->string('product_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Backfill: every existing subscription's inline service_type/
        // product_name/amount becomes its first (only) service row, so no
        // data is lost before those columns are dropped in the next
        // migration.
        DB::table('subscriptions')
            ->select('id', 'service_type', 'product_name', 'amount')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('subscription_services')->insert([
                    'subscription_id' => $row->id,
                    'service_type' => $row->service_type,
                    'product_name' => $row->product_name,
                    'amount' => $row->amount,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_services');
    }
};
