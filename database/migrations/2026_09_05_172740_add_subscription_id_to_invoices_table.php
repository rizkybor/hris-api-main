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
        Schema::table('invoices', function (Blueprint $table) {
            // Nullable + nullOnDelete: most invoices aren't spawned from a
            // subscription, and deleting a subscription must never delete
            // (or block deleting) the invoices it already generated --
            // those are historical financial records.
            $table->foreignId('subscription_id')->nullable()->after('project_id')->constrained('subscriptions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};
