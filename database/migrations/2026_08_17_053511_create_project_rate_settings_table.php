<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('team_monthly_cost', 15, 2);
            $table->decimal('productive_hours_per_person', 8, 2);
            $table->unsignedInteger('team_size');
            $table->decimal('margin_multiplier', 8, 2);
            $table->decimal('pm_overhead_percent', 5, 2)->default(12);
            $table->decimal('default_infra_setup_cost', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_rate_settings');
    }
};
