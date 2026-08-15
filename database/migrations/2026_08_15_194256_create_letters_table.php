<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('letter_number')->unique();
            $table->foreignId('letter_code_id')->constrained('letter_codes')->cascadeOnDelete();
            $table->foreignId('division_code_id')->constrained('division_codes')->cascadeOnDelete();
            $table->enum('type', ['I', 'E']);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->date('date');
            $table->string('subject');
            $table->string('recipient')->nullable();
            $table->longText('body');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->enum('status', ['issued', 'cancelled'])->default('issued');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
