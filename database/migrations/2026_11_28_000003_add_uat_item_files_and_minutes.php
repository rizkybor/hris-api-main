<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - A finding/note can carry a file showing the issue (evidence), and its
 *   revision proof may now be a PDF too (photo_kind: image | file).
 * - Each UAT can have a generated Berita Acara: a numbered letter (code BA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->string('evidence_path')->nullable()->after('description');
            $table->string('evidence_name')->nullable()->after('evidence_path');
            $table->string('evidence_kind', 10)->nullable()->after('evidence_name');
            $table->string('photo_kind', 10)->default('image')->after('photo_name');
        });
        Schema::table('project_uat_sessions', function (Blueprint $table) {
            $table->foreignId('ba_letter_id')->nullable()->after('attachment_size')->constrained('letters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_uat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ba_letter_id');
        });
        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->dropColumn(['evidence_path', 'evidence_name', 'evidence_kind', 'photo_kind']);
        });
    }
};
