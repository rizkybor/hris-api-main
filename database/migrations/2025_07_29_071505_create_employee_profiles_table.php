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
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('identity_number')->unique();
            $table->string('phone');
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('hobby')->nullable();
            $table->string('place_of_birth');
            $table->text('address');
            $table->string('city');
            $table->string('postal_code');
            $table->string('preferred_language')->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes (user_id already indexed by foreign key, code and identity_number already indexed by unique constraints)
            $table->index('phone');
            $table->index('city');
            $table->index('created_at');
            $table->unique('user_id');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
