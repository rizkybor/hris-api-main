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
            $table->integer('no');
            $table->string('sdm_component');
            $table->string('metrik');
            $table->decimal('capacity_target', 15, 2);
            $table->decimal('actual', 15, 2);
            $table->enum('rag_status', ['Red', 'Amber', 'Green']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdm_resources');
    }
};