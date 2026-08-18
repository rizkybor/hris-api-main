<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_letter_id')->constrained('document_letters')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_letter_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_letter_recipients');
    }
};
