<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors_task_payments', function (Blueprint $table) {
            $table->id();
            $table->string('document_name');
            $table->string('document_path')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('payment_date')->nullable(); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors_task_payments');
    }
};
