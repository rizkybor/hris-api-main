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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->date('salary_month');
            $table->date('payment_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('payment_date');
            $table->index('status');
            $table->index(['salary_month', 'status']);
            $table->unique('salary_month');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
