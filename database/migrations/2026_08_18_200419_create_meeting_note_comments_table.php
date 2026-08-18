<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_note_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_note_id')->constrained('meeting_notes')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('meeting_note_comments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('body');
            $table->json('mentioned_employee_ids')->nullable();
            $table->timestamps();

            $table->index(['meeting_note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_note_comments');
    }
};
