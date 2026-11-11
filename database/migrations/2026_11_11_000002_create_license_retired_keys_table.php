<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keys replaced when a license changes plan (the key's code names the
     * plan, so a new plan gets a new key). A retired key never works again;
     * the app is told it was replaced (key_replaced) instead of "unknown".
     */
    public function up(): void
    {
        Schema::create('license_retired_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('plan', 30)->nullable();
            $table->string('reason', 30)->default('plan_changed');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_retired_keys');
    }
};
