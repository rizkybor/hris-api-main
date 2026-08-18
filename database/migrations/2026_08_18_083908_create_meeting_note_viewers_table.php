<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the polling-based "who's viewing this document" presence
     * indicator -- the frontend pings a heartbeat endpoint every few
     * seconds while the detail/edit page is open, and a viewer is
     * considered "active" while last_seen_at is recent (see
     * MeetingNoteController::ACTIVE_VIEWER_WINDOW_SECONDS).
     */
    public function up(): void
    {
        Schema::create('meeting_note_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_note_id')->constrained('meeting_notes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_editing')->default(false);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['meeting_note_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_note_viewers');
    }
};
