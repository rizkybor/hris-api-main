<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versions for Android and iOS, and multiplatform versions (one version
 * line for several platforms, listed in target_platforms). Android versions
 * also note their minimum and target SDK (API level).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->json('target_platforms')->nullable()->after('platforms');
            $table->unsignedSmallInteger('android_min_sdk')->nullable()->after('target_platforms');
            $table->unsignedSmallInteger('android_target_sdk')->nullable()->after('android_min_sdk');
        });
    }

    public function down(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->dropColumn(['target_platforms', 'android_min_sdk', 'android_target_sdk']);
        });
    }
};
