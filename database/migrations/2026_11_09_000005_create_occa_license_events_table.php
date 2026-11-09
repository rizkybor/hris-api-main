<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-license tracking: admin changes, device activity from the app and
     * denied attempts. Append-only, so there is no updated_at. No foreign
     * key: events of a (soft-)deleted license are kept.
     */
    public function up(): void
    {
        Schema::create('occa_license_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occa_license_id')->index();
            $table->string('event', 50);
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('ip', 45)->nullable();
            // The admin who did it; null for app events.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['occa_license_id', 'event', 'device_id', 'created_at'], 'occa_license_events_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occa_license_events');
    }
};
