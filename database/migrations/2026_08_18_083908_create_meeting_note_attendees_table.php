<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_note_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_note_id')->constrained('meeting_notes')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_note_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_note_attendees');
    }
};
