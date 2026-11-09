<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devices (Mac / iPad installs) a license key is activated on.
     */
    public function up(): void
    {
        Schema::create('occa_license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occa_license_id')->constrained('occa_licenses')->cascadeOnDelete();
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->dateTime('activated_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->unique(['occa_license_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occa_license_activations');
    }
};
