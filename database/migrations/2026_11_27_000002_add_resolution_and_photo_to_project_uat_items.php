<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A UAT finding/note can be checked off once it is revised/updated (who
 * and when), and can carry one optional proof photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->boolean('is_resolved')->default(false)->after('description');
            $table->timestamp('resolved_at')->nullable()->after('is_resolved');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->string('photo_path')->nullable()->after('resolved_by');
            $table->string('photo_name')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('project_uat_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['is_resolved', 'resolved_at', 'photo_path', 'photo_name']);
        });
    }
};
