<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            // Public share link: anyone holding the (unguessable) token can
            // open the proposal's PDF without logging in. Null = not shared.
            $table->string('share_token', 64)->nullable()->unique()->after('use_signature');
            $table->timestamp('share_expires_at')->nullable()->after('share_token');
            $table->unsignedInteger('share_view_count')->default(0)->after('share_expires_at');
            $table->timestamp('share_last_viewed_at')->nullable()->after('share_view_count');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'share_expires_at', 'share_view_count', 'share_last_viewed_at']);
        });
    }
};
