<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->enum('type', ['I', 'E']);
            $table->date('date');
            $table->string('title');
            $table->string('client_name');
            $table->text('client_address')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_wa')->nullable();
            $table->json('items');
            $table->decimal('total', 18, 2)->default(0);
            $table->json('payment_terms')->nullable();
            $table->text('general_terms')->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->unsignedInteger('replacement_days')->nullable();
            $table->string('buyer_signatory_name')->nullable();
            $table->string('buyer_signatory_title')->nullable();
            $table->string('vendor_signatory_name')->nullable();
            $table->string('vendor_signatory_title')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
