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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('expected_size')->nullable();
            $table->text('description')->nullable();
            $table->string('icon');
            $table->string('department');
            $table->string('status')->default('active');
            $table->foreignId('team_lead_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('responsibilities');
            $table->timestamps();
            $table->softDeletes();

            // Indexes (team_lead_id already indexed by foreign key)
            $table->index('name');
            $table->index('department');
            $table->index('status');
            $table->index(['department', 'status']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
