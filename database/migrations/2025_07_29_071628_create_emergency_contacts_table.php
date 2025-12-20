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
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->onDelete('cascade');
            $table->string('full_name')->nullable();
            $table->string('relationship');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes (employee_id already indexed by foreign key)
            $table->index('phone');
            $table->index('email');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};
