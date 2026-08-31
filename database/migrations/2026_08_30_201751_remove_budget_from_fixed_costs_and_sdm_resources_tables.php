<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->dropColumn('budget');
        });

        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->dropColumn(['budget', 'metrik']);
        });
    }

    public function down(): void
    {
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->decimal('budget', 15, 2)->nullable()->after('description');
        });

        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->decimal('budget', 15, 2)->nullable()->after('capacity_target');
            $table->string('metrik')->nullable()->after('sdm_component');
        });
    }
};
