<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Licenses generated together for "Mac + iPad" share a bundle id: one
     * licensee, plan and period, a Mac key and an iPad key.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->uuid('bundle_id')->nullable()->after('platform')->index();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['bundle_id']);
            $table->dropColumn('bundle_id');
        });
    }
};
