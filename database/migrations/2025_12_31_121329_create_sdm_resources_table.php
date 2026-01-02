<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdm_resources', function (Blueprint $table) {
            $table->id();
            $table->string('sdm_component');
            $table->string('metrik');
            $table->string('capacity_target');
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('actual', 15, 2)->nullable();
            $table->string('rag_status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdm_resources');
    }
};
