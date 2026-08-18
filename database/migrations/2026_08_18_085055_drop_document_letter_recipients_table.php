<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Official Memo's recipient is always Finance Manager -- the sole
     * approver role, and there's only ever one such account -- so the
     * Team-based "Unit Tujuan" multi-select this table backed is no
     * longer needed.
     */
    public function up(): void
    {
        Schema::dropIfExists('document_letter_recipients');
    }

    public function down(): void
    {
        Schema::create('document_letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_letter_id')->constrained('document_letters')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_letter_id', 'team_id']);
        });
    }
};
