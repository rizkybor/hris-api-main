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
        Schema::create('bank_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_holder_name');
            $table->string('bank_branch')->nullable();
            $table->string('account_type');
            $table->timestamps();
            $table->softDeletes();

            // Indexes (employee_id already indexed by foreign key)
            $table->index('bank_name');
            $table->index('account_number');
            $table->index('account_type');
            $table->unique('account_number');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_information');
    }
};
